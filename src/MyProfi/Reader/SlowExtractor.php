<?php

namespace MyProfi\Reader;

    /**
     * MyProfi is mysql profiler and analizer, which outputs statistics of mostly
     * used queries by reading query log file.
     *
     * Copyright (C) 2006 camka at camka@users.sourceforge.net
     * 2016 - Marcus Schwarz <github@maswaba.de>
     *
     * This program is free software; you can redistribute it and/or
     * modify it under the terms of the GNU General Public License
     * as published by the Free Software Foundation; either version 2
     * of the License, or (at your option) any later version.
     *
     * This program is distributed in the hope that it will be useful,
     * but WITHOUT ANY WARRANTY; without even the implied warranty of
     * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     * GNU General Public License for more details.
     *
     * @author  camka
     * @package MyProfi
     */

/**
 * Extracts normalized queries from mysql slow query log one by one
 *
 */
class SlowExtractor extends Filereader implements IQueryFetcher
{

    protected $stat = [];

    protected array $context = [
        'thread_id' => null,
        'user' => null,
        'host' => null,
        'timestamp' => null,
        'schema' => null,
    ];

    /**
     * Fetch the next query pattern from stream
     *
     * @return string
     */
    public function getQuery()
    {
        $currstatement = '';
        // Saved context/stat for the current query being built
        $savedContext = $this->context;
        $savedStat = $this->stat;

        $fp = $this->fp;

        while (($line = fgets($fp))) {
            $line = rtrim($line, "\r\n");

            // Save context/stat BEFORE isSeparator potentially modifies them
            $preContext = $this->context;
            $preStat = $this->stat;

            if (($smth = $this->isSeparator($line, $fp))) {
                if (is_array($smth)) {
                    $this->stat = $smth;
                }

                if ($currstatement !== '') {
                    // Return with saved context/stat (from before this separator)
                    $result = array_merge($savedStat, $savedContext, ['sql' => $currstatement]);
                    return $result;
                }

                // No statement yet, update saved state for next query
                // Use pre-separator context if this was a context-modifying line
                $savedContext = $this->context;
                $savedStat = $this->stat;
            } else {
                $currstatement .= $line;
            }
        }

        if ($currstatement !== '') {
            return array_merge($savedStat, $savedContext, ['sql' => $currstatement]);
        } else {
            return false;
        }
    }

    /**
     * @param $line
     * @param $fp
     *
     * @return array|bool
     */
    protected function isSeparator(&$line, $fp)
    {
        // skip server start log lines
        /*
          /usr/sbin/mysqld, Version: 5.0.26-log. started with:
          Tcp port: 3306  Unix socket: /var/lib/mysql/mysqldb/mysql.sock
          Time                 Id Command    Argument
          */
        if (substr($line, -13) === 'started with:') {
            fgets($fp); // skip TCP Port: 3306, Named Pipe: (null)
            fgets($fp); // skip Time                 Id Command    Argument
            return true;
        }

        // skip command information
        # Time: 070103 16:53:22
        # User@Host: photo[photo] @ dopey [192.168.16.70]
        # Query_time: 14  Lock_time: 0  Rows_sent: 93  Rows_examined: 3891399

        $linestart = substr($line, 0, 14);

        // Parse # Time: 161019 21:48:33 or # Time: 2016-10-19T21:48:33
        if (!strncmp($linestart, '# Time: ', 8)) {
            $timeStr = substr($line, 8);
            // Try parsing different timestamp formats
            if (preg_match('/^(\d{6})\s+(\d{1,2}:\d{2}:\d{2})/', $timeStr, $m)) {
                // Format: YYMMDD HH:MM:SS
                $this->context['timestamp'] = '20' . substr($m[1], 0, 2) . '-' .
                    substr($m[1], 2, 2) . '-' . substr($m[1], 4, 2) . ' ' . $m[2];
            } elseif (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}:\d{2})/', $timeStr, $m)) {
                // Format: YYYY-MM-DDTHH:MM:SS or YYYY-MM-DD HH:MM:SS
                $this->context['timestamp'] = $m[1] . ' ' . $m[2];
            }
            return true;
        }

        // Parse # User@Host: root[root] @ localhost []  Id: 19928868
        // or # User@Host: root[root] @ localhost []
        if (!strncmp($line, '# User@Host: ', 13)) {
            $userHost = substr($line, 13);
            // Extract user (before [ or @)
            if (preg_match('/^(\w+)/', $userHost, $m)) {
                $this->context['user'] = $m[1];
            }
            // Extract host (after @ and before [ or Id:)
            if (preg_match('/@\s*(\S+)\s*\[/', $userHost, $m)) {
                $this->context['host'] = $m[1];
            }
            // Extract thread_id from Id: at the end (Percona format)
            if (preg_match('/Id:\s*(\d+)/', $userHost, $m)) {
                $this->context['thread_id'] = $m[1];
            }
            return true;
        }

        // Parse # Thread_id: 123  Schema: dbname  QC_hit: No (Percona format)
        if (!strncmp($line, '# Thread_id: ', 13)) {
            $rest = substr($line, 13);
            // Extract just the numeric thread_id
            if (preg_match('/^(\d+)/', $rest, $m)) {
                $this->context['thread_id'] = $m[1];
            }
            // Extract schema if present
            if (preg_match('/Schema:\s*(\S+)/', $rest, $m)) {
                $this->context['schema'] = $m[1];
            }
            return true;
        }

        // Parse # Schema: mydb ...
        if (!strncmp($line, '# Schema: ', 10)) {
            $schemaLine = substr($line, 10);
            if (preg_match('/^(\S+)/', $schemaLine, $m)) {
                $this->context['schema'] = $m[1];
            }
            return true;
        }

        if (!strncmp($line, '# Bytes_sent: ', 14) || // todo maybe bytes_sent could be used for the statistics
            !strncmp($line, '# Rows_affected: ', 17)
        ) {
            return true;
        }

        if (!strncmp($line, '# Query_time: ', 14)) {
            $matches = [];

            // floating point numbers matching is needed for
            // www.mysqlperformanceblog.com slow query patch
            preg_match(
                '/Query_time: +(\\d*(?:\\.\\d+)?) +Lock_time: +(\\d*(?:\\.\\d+)?) +Rows_sent: +(\\d+) +Rows_examined: +(\\d+)/',
                $line,
                $matches
            );

            // shift the whole matched string element
            // leaving only numbers we need
            array_shift($matches);
            $arr = [
                'qt' => array_shift($matches),
                'lt' => array_shift($matches),
                'rs' => array_shift($matches),
                're' => array_shift($matches),
            ];

            return $arr;
        }

        if (preg_match('/(?:^use [^ ]+;$)|(?:^SET timestamp=\\d+;$)/', $line)) {
            return true;
        }

        return false;
    }
}
