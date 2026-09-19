<?php

    function renderMarkdownInline($text) {
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            $url = filter_var($m[2], FILTER_VALIDATE_URL) ? $m[2] : '#';
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
        }, $text);
        return $text;
    }

    function renderMarkdownTable($lines) {
        if (count($lines) < 2) return '';
        $headerCells = array_map('trim', explode('|', trim($lines[0], '| ')));
        $rows = array_slice($lines, 2);
        $html = '<table class="table table-dark table-bordered table-sm"><thead><tr>';
        foreach ($headerCells as $cell) {
            $html .= '<th>' . renderMarkdownInline($cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            if (trim($row) === '') continue;
            $cells = array_map('trim', explode('|', trim($row, '| ')));
            $html .= '<tr>';
            foreach ($cells as $cell) {
                $html .= '<td>' . renderMarkdownInline($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    function renderMarkdown($markdown) {
        if ($markdown === null || trim($markdown) === '') return '<p class="text-muted">Noch kein Protokoll vorhanden.</p>';

        $escaped = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');
        $lines = explode("\n", $escaped);
        $html = '';
        $i = 0;
        $n = count($lines);
        $paragraphBuffer = [];

        while ($i < $n) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '') {
                if (!empty($paragraphBuffer)) {
                    $html .= '<p>' . implode('<br>', array_map('renderMarkdownInline', $paragraphBuffer)) . '</p>';
                    $paragraphBuffer = [];
                }
                $i++;
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.*)$/', $trimmed, $m)) {
                if (!empty($paragraphBuffer)) {
                    $html .= '<p>' . implode('<br>', array_map('renderMarkdownInline', $paragraphBuffer)) . '</p>';
                    $paragraphBuffer = [];
                }
                $level = strlen($m[1]);
                $html .= "<h{$level}>" . renderMarkdownInline($m[2]) . "</h{$level}>";
                $i++;
                continue;
            }

            if (strpos($trimmed, '|') !== false && isset($lines[$i + 1]) && preg_match('/^\s*\|?[\s:\-\|]+\|?\s*$/', $lines[$i + 1]) && trim($lines[$i + 1]) !== '') {
                if (!empty($paragraphBuffer)) {
                    $html .= '<p>' . implode('<br>', array_map('renderMarkdownInline', $paragraphBuffer)) . '</p>';
                    $paragraphBuffer = [];
                }
                $tableLines = [];
                while ($i < $n && strpos(trim($lines[$i]), '|') !== false) {
                    $tableLines[] = trim($lines[$i]);
                    $i++;
                }
                $html .= renderMarkdownTable($tableLines);
                continue;
            }

            if (preg_match('/^-\s+(.*)$/', $trimmed)) {
                if (!empty($paragraphBuffer)) {
                    $html .= '<p>' . implode('<br>', array_map('renderMarkdownInline', $paragraphBuffer)) . '</p>';
                    $paragraphBuffer = [];
                }
                $items = [];
                while ($i < $n && preg_match('/^-\s+(.*)$/', trim($lines[$i]), $mm)) {
                    $items[] = renderMarkdownInline($mm[1]);
                    $i++;
                }
                $html .= '<ul>' . implode('', array_map(function ($it) { return "<li>$it</li>"; }, $items)) . '</ul>';
                continue;
            }

            if (preg_match('/^\d+\.\s+(.*)$/', $trimmed)) {
                if (!empty($paragraphBuffer)) {
                    $html .= '<p>' . implode('<br>', array_map('renderMarkdownInline', $paragraphBuffer)) . '</p>';
                    $paragraphBuffer = [];
                }
                $items = [];
                while ($i < $n && preg_match('/^\d+\.\s+(.*)$/', trim($lines[$i]), $mm)) {
                    $items[] = renderMarkdownInline($mm[1]);
                    $i++;
                }
                $html .= '<ol>' . implode('', array_map(function ($it) { return "<li>$it</li>"; }, $items)) . '</ol>';
                continue;
            }

            $paragraphBuffer[] = $line;
            $i++;
        }

        if (!empty($paragraphBuffer)) {
            $html .= '<p>' . implode('<br>', array_map('renderMarkdownInline', $paragraphBuffer)) . '</p>';
        }

        return $html;
    }