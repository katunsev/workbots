<?php
$tasksFile = __DIR__ . '../config/tasks.json';

$locksDir = __DIR__ . '/locks';

if (!file_exists($locksDir)) {
    mkdir($locksDir, 0777, true);
}

if (!file_exists($tasksFile)) {
    die("No tasks.json found!\n");
}
$tasks = json_decode(file_get_contents($tasksFile), true);
if (!$tasks) {
    die("Invalid tasks.json\n");
}

function cron_matches($cron_expression, $time = null) {
    $time = $time ?: time();
    $cron_parts = preg_split('/\s+/', $cron_expression);
    if (count($cron_parts) != 5) return false;
    list($min, $hour, $dom, $mon, $dow) = $cron_parts;
    $date = [
        'min'  => (int)date('i', $time),
        'hour' => (int)date('G', $time),
        'dom'  => (int)date('j', $time),
        'mon'  => (int)date('n', $time),
        'dow'  => (int)date('w', $time),
    ];
    foreach (['min'=>$min, 'hour'=>$hour, 'dom'=>$dom, 'mon'=>$mon, 'dow'=>$dow] as $unit => $expr) {
        if (!cron_unit_matches($expr, $date[$unit])) {
            return false;
        }
    }
    return true;
}

function cron_unit_matches($expr, $val) {
    foreach (explode(',', $expr) as $part) {
        $part = trim($part);
        if ($part === '*') return true;
        if (preg_match('/^(\d+)-(\d+)(\/(\d+))?$/', $part, $m)) {
            $start = (int)$m[1]; $end = (int)$m[2];
            $step = isset($m[4]) ? (int)$m[4] : 1;
            if ($val >= $start && $val <= $end && (($val-$start) % $step) == 0) return true;
        } elseif (preg_match('/^\*(\/(\d+))?$/', $part, $m)) {
            $step = isset($m[2]) ? (int)$m[2] : 1;
            if ($val % $step == 0) return true;
        } elseif ((string)$val === $part) {
            return true;
        }
    }
    return false;
}

function get_lock_file($command, $locksDir) {
    return $locksDir . '/' . md5($command) . '.lock';
}

function is_locked($lock_file) {
    if (!file_exists($lock_file)) return false;
    $pid = (int)@file_get_contents($lock_file);
    if ($pid > 0 && posix_kill($pid, 0)) {
        return true;
    } else {
        @unlink($lock_file);
        return false;
    }
}

function set_lock($lock_file, $pid) {
    file_put_contents($lock_file, $pid);
}

function remove_lock($lock_file) {
    @unlink($lock_file);
}

foreach ($tasks as $task) {
    $cmd = $task['command'];
    $cron = $task['schedule'];
    $lock_file = get_lock_file($cmd, $locksDir);

    if (cron_matches($cron)) {
        if (is_locked($lock_file)) {
            echo "Задача уже выполняется: $cmd\n";
            continue;
        }

        $runCmd = $cmd . ' > /dev/null 2>&1 & echo $!';
        $pid = (int)shell_exec($runCmd);
        if ($pid > 0) {
            set_lock($lock_file, $pid);
            echo "Старт задачи $cmd (PID $pid)\n";
        }
    }
}
?>
