<?php

namespace MyProfi\Template;

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
 * Dynamic HTML template with embedded JavaScript for interactive filtering
 */
class DynamicTemplate implements ITemplate
{
    private array $typeRows = [];
    private array $mainRows = [];
    private array $executions;
    private array $uniqueThreads;
    private array $uniqueUsers;
    private array $thresholds;
    private int $totalQueries = 0;

    public function __construct(
        array $executions,
        array $uniqueThreads,
        array $uniqueUsers,
        array $thresholds = []
    ) {
        $this->executions = $executions;
        $this->uniqueThreads = $uniqueThreads;
        $this->uniqueUsers = $uniqueUsers;
        $this->thresholds = array_merge([
            'qt_slow' => 1.0,
            'qt_critical' => 5.0,
        ], $thresholds);
    }

    public function miniheader()
    {
        // Buffered - no output yet
    }

    public function minirow($type, $num, $percent)
    {
        $this->typeRows[] = [
            'type' => $type,
            'num' => $num,
            'percent' => $percent,
        ];
    }

    public function minifooter($total)
    {
        $this->totalQueries = $total;
    }

    public function mainheader()
    {
        // Buffered - no output yet
    }

    public function mainrow($ornum, $num, $percent, $query, $sort = false, $smpl = false)
    {
        $this->mainRows[] = [
            'ornum' => $ornum,
            'num' => $num,
            'percent' => $percent,
            'query' => $query,
            'sort' => $sort,
            'smpl' => $smpl,
        ];
    }

    public function mainfooter($total)
    {
        $this->outputHtml($total);
    }

    private function outputHtml(int $totalPatterns): void
    {
        $typeRowsJson = json_encode($this->typeRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $mainRowsJson = json_encode($this->mainRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $executionsJson = json_encode($this->executions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $threadsJson = json_encode($this->uniqueThreads, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $usersJson = json_encode($this->uniqueUsers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $thresholdsJson = json_encode($this->thresholds, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyProfi Dynamic Report</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 13px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            font-size: 24px;
            margin: 0 0 20px 0;
            color: #333;
        }
        h2 {
            font-size: 16px;
            margin: 20px 0 10px 0;
            color: #333;
        }
        .filter-panel {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-group label {
            font-weight: 600;
            color: #555;
        }
        select, button {
            padding: 6px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
        }
        select {
            min-width: 150px;
            background: #fff;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 500;
        }
        button:hover {
            background: #0056b3;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        button.secondary {
            background: #6c757d;
        }
        button.secondary:hover {
            background: #545b62;
        }
        .stats-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 60px;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .query-cell {
            font-family: "SF Mono", Monaco, "Courier New", monospace;
            font-size: 11px;
            word-break: break-all;
            max-width: 600px;
        }
        .sample-row {
            background: #f9f9f9;
        }
        .sample-row td {
            padding: 5px 10px;
        }
        .sample-row textarea {
            width: 100%;
            min-height: 60px;
            font-family: "SF Mono", Monaco, "Courier New", monospace;
            font-size: 11px;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 8px;
            resize: vertical;
        }
        .num-cell {
            text-align: right;
            font-family: "SF Mono", Monaco, "Courier New", monospace;
        }
        .time-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 500;
        }
        .time-badge.normal {
            background: #d4edda;
            color: #155724;
        }
        .time-badge.slow {
            background: #fff3cd;
            color: #856404;
        }
        .time-badge.critical {
            background: #f8d7da;
            color: #721c24;
        }
        .query-row.slow {
            background: #fff9e6;
        }
        .query-row.critical {
            background: #ffe6e6;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal {
            background: #fff;
            border-radius: 8px;
            max-width: 90%;
            max-height: 90%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            line-height: 1;
        }
        .modal-close:hover {
            color: #333;
        }
        .modal-body {
            padding: 20px;
            overflow: auto;
            max-height: 70vh;
        }
        .timeline-table {
            min-width: 800px;
        }
        .timeline-table th {
            position: static;
        }
        .filter-info {
            color: #666;
            font-style: italic;
        }
        .totals {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            font-weight: 500;
        }
        .sortable {
            cursor: pointer;
            user-select: none;
        }
        .sortable:hover {
            background: #e9ecef;
        }
        .sortable::after {
            content: ' \\2195';
            opacity: 0.3;
        }
        .sortable.asc::after {
            content: ' \\2191';
            opacity: 1;
        }
        .sortable.desc::after {
            content: ' \\2193';
            opacity: 1;
        }
    </style>
</head>
<body>
    <h1>MyProfi Dynamic Report</h1>

    <div class="filter-panel">
        <div class="filter-group">
            <label for="thread-filter">Thread ID:</label>
            <select id="thread-filter">
                <option value="">All Threads</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="user-filter">User:</label>
            <select id="user-filter">
                <option value="">All Users</option>
            </select>
        </div>
        <button id="view-timeline" disabled>View Thread Timeline</button>
        <button id="reset-filters" class="secondary">Reset Filters</button>
        <span class="filter-info" id="filter-info"></span>
    </div>

    <div class="stats-container">
        <h2>Queries by Type</h2>
        <table id="type-stats">
            <thead>
                <tr>
                    <th>Type</th>
                    <th class="num-cell">Count</th>
                    <th class="num-cell">Percentage</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="totals" id="type-totals"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="stats-container">
        <h2>Queries by Pattern</h2>
        <table id="pattern-stats">
            <thead>
                <tr>
                    <th class="sortable" data-sort="ornum">#</th>
                    <th class="sortable num-cell" data-sort="num">Count</th>
                    <th class="sortable num-cell" data-sort="percent">%/Value</th>
                    <th>Query Pattern</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="totals" id="pattern-totals"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="modal-overlay" id="timeline-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>Thread Timeline: <span id="timeline-thread-id"></span></h3>
                <button class="modal-close" id="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <table class="timeline-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th class="num-cell">Query Time</th>
                            <th class="num-cell">Lock Time</th>
                            <th class="num-cell">Rows Sent</th>
                            <th class="num-cell">Rows Examined</th>
                            <th>Query Pattern</th>
                        </tr>
                    </thead>
                    <tbody id="timeline-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    (function() {
        // Data from PHP
        const DATA = {
            typeRows: {$typeRowsJson},
            mainRows: {$mainRowsJson},
            executions: {$executionsJson},
            threads: {$threadsJson},
            users: {$usersJson},
            thresholds: {$thresholdsJson},
            totalQueries: {$this->totalQueries},
            totalPatterns: {$totalPatterns}
        };

        // State
        let currentThreadFilter = '';
        let currentUserFilter = '';
        let sortColumn = 'ornum';
        let sortDirection = 'asc';

        // DOM Elements
        const threadSelect = document.getElementById('thread-filter');
        const userSelect = document.getElementById('user-filter');
        const viewTimelineBtn = document.getElementById('view-timeline');
        const resetBtn = document.getElementById('reset-filters');
        const filterInfo = document.getElementById('filter-info');
        const typeStatsBody = document.querySelector('#type-stats tbody');
        const patternStatsBody = document.querySelector('#pattern-stats tbody');
        const typeTotals = document.getElementById('type-totals');
        const patternTotals = document.getElementById('pattern-totals');
        const timelineModal = document.getElementById('timeline-modal');
        const timelineThreadId = document.getElementById('timeline-thread-id');
        const timelineBody = document.getElementById('timeline-body');
        const closeModal = document.getElementById('close-modal');

        // Initialize
        function init() {
            populateFilters();
            renderTypeStats();
            renderPatternStats();
            setupEventListeners();
        }

        function populateFilters() {
            DATA.threads.forEach(thread => {
                const opt = document.createElement('option');
                opt.value = thread;
                opt.textContent = thread;
                threadSelect.appendChild(opt);
            });

            DATA.users.forEach(user => {
                const opt = document.createElement('option');
                opt.value = user;
                opt.textContent = user;
                userSelect.appendChild(opt);
            });
        }

        function setupEventListeners() {
            threadSelect.addEventListener('change', () => {
                currentThreadFilter = threadSelect.value;
                viewTimelineBtn.disabled = !currentThreadFilter;
                applyFilters();
            });

            userSelect.addEventListener('change', () => {
                currentUserFilter = userSelect.value;
                applyFilters();
            });

            resetBtn.addEventListener('click', () => {
                threadSelect.value = '';
                userSelect.value = '';
                currentThreadFilter = '';
                currentUserFilter = '';
                viewTimelineBtn.disabled = true;
                applyFilters();
            });

            viewTimelineBtn.addEventListener('click', () => {
                if (currentThreadFilter) {
                    showThreadTimeline(currentThreadFilter);
                }
            });

            closeModal.addEventListener('click', () => {
                timelineModal.classList.remove('active');
            });

            timelineModal.addEventListener('click', (e) => {
                if (e.target === timelineModal) {
                    timelineModal.classList.remove('active');
                }
            });

            // Sortable columns
            document.querySelectorAll('.sortable').forEach(th => {
                th.addEventListener('click', () => {
                    const col = th.dataset.sort;
                    if (sortColumn === col) {
                        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortColumn = col;
                        sortDirection = 'asc';
                    }
                    updateSortIndicators();
                    renderPatternStats();
                });
            });
        }

        function updateSortIndicators() {
            document.querySelectorAll('.sortable').forEach(th => {
                th.classList.remove('asc', 'desc');
                if (th.dataset.sort === sortColumn) {
                    th.classList.add(sortDirection);
                }
            });
        }

        function getFilteredExecutions() {
            return DATA.executions.filter(exec => {
                if (currentThreadFilter && exec.thread_id != currentThreadFilter) return false;
                if (currentUserFilter && exec.user !== currentUserFilter) return false;
                return true;
            });
        }

        function applyFilters() {
            const filtered = getFilteredExecutions();

            // Update filter info
            if (currentThreadFilter || currentUserFilter) {
                const parts = [];
                if (currentThreadFilter) parts.push('Thread: ' + currentThreadFilter);
                if (currentUserFilter) parts.push('User: ' + currentUserFilter);
                filterInfo.textContent = 'Filtering by ' + parts.join(', ') + ' (' + filtered.length + ' executions)';
            } else {
                filterInfo.textContent = '';
            }

            renderTypeStats(filtered);
            renderPatternStats(filtered);
        }

        function renderTypeStats(filteredExecs) {
            const execs = filteredExecs || DATA.executions;

            // Calculate type stats from filtered executions
            const typeMap = {};
            execs.forEach(exec => {
                const type = exec.pattern.split(/[^a-z]/i)[0].toLowerCase();
                typeMap[type] = (typeMap[type] || 0) + 1;
            });

            // Sort by count descending
            const sortedTypes = Object.entries(typeMap).sort((a, b) => b[1] - a[1]);
            const total = execs.length;

            typeStatsBody.innerHTML = '';
            sortedTypes.forEach(([type, count]) => {
                const percent = total > 0 ? (100 * count / total) : 0;
                const row = document.createElement('tr');
                row.innerHTML = '<td>' + escapeHtml(type) + '</td>' +
                    '<td class="num-cell">' + formatNumber(count) + '</td>' +
                    '<td class="num-cell">' + percent.toFixed(2) + '%</td>';
                typeStatsBody.appendChild(row);
            });

            typeTotals.textContent = 'Total: ' + formatNumber(total) + ' queries';
        }

        function renderPatternStats(filteredExecs) {
            const execs = filteredExecs || DATA.executions;

            // Calculate pattern stats from filtered executions
            const patternMap = {};
            execs.forEach(exec => {
                if (!patternMap[exec.hash]) {
                    patternMap[exec.hash] = {
                        pattern: exec.pattern,
                        count: 0,
                        totalQt: 0,
                        maxQt: 0
                    };
                }
                patternMap[exec.hash].count++;
                patternMap[exec.hash].totalQt += parseFloat(exec.qt) || 0;
                patternMap[exec.hash].maxQt = Math.max(patternMap[exec.hash].maxQt, parseFloat(exec.qt) || 0);
            });

            // Convert to array and sort
            let patterns = Object.values(patternMap);
            const total = execs.length;

            // Sort patterns
            patterns.sort((a, b) => {
                let aVal, bVal;
                switch(sortColumn) {
                    case 'num':
                        aVal = a.count;
                        bVal = b.count;
                        break;
                    case 'percent':
                        aVal = a.count / total;
                        bVal = b.count / total;
                        break;
                    default:
                        aVal = a.count;
                        bVal = b.count;
                }
                return sortDirection === 'asc' ? aVal - bVal : bVal - aVal;
            });

            patternStatsBody.innerHTML = '';
            patterns.forEach((p, idx) => {
                const percent = total > 0 ? (100 * p.count / total) : 0;
                const severityClass = getSeverityClass(p.maxQt);

                const row = document.createElement('tr');
                row.className = 'query-row ' + severityClass;
                row.innerHTML = '<td>' + (idx + 1) + '</td>' +
                    '<td class="num-cell">' + formatNumber(p.count) + '</td>' +
                    '<td class="num-cell">' + percent.toFixed(2) + '%</td>' +
                    '<td class="query-cell">' + escapeHtml(p.pattern) + '</td>';
                patternStatsBody.appendChild(row);
            });

            patternTotals.textContent = 'Total: ' + formatNumber(patterns.length) + ' patterns from ' + formatNumber(total) + ' executions';
        }

        function showThreadTimeline(threadId) {
            const threadExecs = DATA.executions
                .filter(exec => exec.thread_id == threadId)
                .sort((a, b) => {
                    if (!a.timestamp || !b.timestamp) return 0;
                    return a.timestamp.localeCompare(b.timestamp);
                });

            timelineThreadId.textContent = threadId;
            timelineBody.innerHTML = '';

            threadExecs.forEach((exec, idx) => {
                const qtClass = getSeverityClass(exec.qt);
                const row = document.createElement('tr');
                row.className = 'query-row ' + qtClass;
                row.innerHTML = '<td>' + (idx + 1) + '</td>' +
                    '<td>' + (exec.timestamp || '-') + '</td>' +
                    '<td>' + escapeHtml(exec.user || '-') + '</td>' +
                    '<td class="num-cell"><span class="time-badge ' + qtClass + '">' + formatTime(exec.qt) + '</span></td>' +
                    '<td class="num-cell">' + formatTime(exec.lt) + '</td>' +
                    '<td class="num-cell">' + formatNumber(exec.rs) + '</td>' +
                    '<td class="num-cell">' + formatNumber(exec.re) + '</td>' +
                    '<td class="query-cell">' + escapeHtml(exec.pattern) + '</td>';
                timelineBody.appendChild(row);
            });

            timelineModal.classList.add('active');
        }

        function getSeverityClass(qt) {
            const time = parseFloat(qt) || 0;
            if (time >= DATA.thresholds.qt_critical) return 'critical';
            if (time >= DATA.thresholds.qt_slow) return 'slow';
            return 'normal';
        }

        function formatNumber(num) {
            return new Intl.NumberFormat().format(num);
        }

        function formatTime(time) {
            const t = parseFloat(time) || 0;
            return t.toFixed(6);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>
</body>
</html>
HTML;
    }
}
