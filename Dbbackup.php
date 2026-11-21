<?php
/**
 * dbbackup.php : DB 전체 백업 화면 (PDO)
 *
 * - 상단 [지금 백업] 버튼을 누르면 현재 DB 의 모든 테이블 데이터를 INSERT 문으로 ./db/오늘날짜.sql 에 저장한다.
 *     · 저장된 .sql 은 DB 툴(phpMyAdmin, DBeaver, HeidiSQL 등)에서 그대로 실행할 수 있다.
 *     · 같은 날 다시 백업하면 오늘 파일을 새 내용으로 교체한다. (임시 파일에 먼저 쓰고 성공하면 교체하므로 실패해도 기존 파일은 안전)
 *     · [테이블 구조 포함] 을 체크하면 CREATE TABLE IF NOT EXISTS 문도 함께 저장한다. (빈 DB 에 복원할 때 사용)
 * - 하단에는 ./db/ 폴더(하위 폴더 포함)의 모든 파일 목록이 최신순으로 나오고, 각 파일을 다운로드할 수 있다.
 * - menu.php 의 loadPage('dbbackup.php', 'DB 백업') 으로 #content-area 에 로드되는 화면 조각이며,
 *   같은 파일이 AJAX(JSON) 와 다운로드 요청도 처리한다.
 *     GET  ?action=list             : 백업 파일 목록
 *     POST action=backup            : 백업 실행
 *     GET  ?action=download&file=.. : 파일 다운로드
 *
 * [주의] 이 페이지에는 접근 제한이 없다. 주소를 아는 사람은 누구나 백업/다운로드를 할 수 있으므로
 *        외부에 공개된 서버라면 최소한 서버 설정(Basic 인증, IP 제한 등)으로 보호하는 것을 권장한다.
 *        ./db/ 폴더에는 직접 접근을 막는 .htaccess 를 자동 생성한다. (Apache 전용 / Nginx 는 서버 설정으로 막아야 함)
 *        백업 대상은 DB 데이터이며 첨부파일(attachment 테이블이 가리키는 실제 파일)은 포함되지 않는다.
 */
declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';   // ← 추가 (맨 위)
date_default_timezone_set('Asia/Seoul');

/* --------------------------------------------------
 * DB 연결 설정
 * -------------------------------------------------- */
require_once __DIR__ . '/db_connect.php';

/* --------------------------------------------------
 * 설정
 * -------------------------------------------------- */
$bkDir = __DIR__ . '/db';   // 백업 파일 저장 폴더 (./db/)

/* --------------------------------------------------
 * 공통 함수
 * -------------------------------------------------- */
if (!function_exists('bk_json')) {
    function bk_json(array $data, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 백업 폴더 준비 : 없으면 생성 + 웹에서 직접 접근하지 못하도록 .htaccess / index.html 생성
    function bk_prepare_dir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('백업 폴더(./db/)를 만들 수 없습니다. 서버의 쓰기 권한을 확인해 주세요.');
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('백업 폴더(./db/)에 쓸 수 없습니다. 서버의 쓰기 권한을 확인해 주세요.');
        }
        $ht = $dir . '/.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht, "# 백업 파일 직접 접근 차단 (다운로드는 dbbackup.php 를 통해서만 가능)\n"
                . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        }
        $idx = $dir . '/index.html';
        if (!file_exists($idx)) {
            @file_put_contents($idx, '');
        }
    }

    function bk_quote_ident(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    function bk_format_size(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return number_format($bytes / 1024 / 1024, 1) . ' MB';
        return number_format($bytes / 1024 / 1024 / 1024, 2) . ' GB';
    }

    // ./db/ 하위의 모든 파일(숨김 파일, index.html 제외) 목록 - 최신순
    function bk_list_files(string $dir): array
    {
        $base = realpath($dir);
        if ($base === false || !is_dir($base)) return [];

        $files = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
            $hidden = false;
            foreach (explode('/', $rel) as $part) {
                if ($part !== '' && $part[0] === '.') { $hidden = true; break; }
            }
            if ($hidden || $rel === 'index.html') continue;

            $files[] = [
                'name'      => $rel,
                'size'      => (int)$f->getSize(),
                'size_text' => bk_format_size((int)$f->getSize()),
                'mtime'     => (int)$f->getMTime(),
                'mtime_text' => date('Y-m-d H:i:s', (int)$f->getMTime())
            ];
        }
        usort($files, function ($a, $b) {
            return [$b['mtime'], $b['name']] <=> [$a['mtime'], $a['name']];
        });
        return $files;
    }

    // 다운로드 요청 파일 경로 검증 : ./db/ 폴더 안의 숨김 아닌 파일만 허용 (.. 등 경로 이탈 차단)
    function bk_resolve_download(string $dir, string $rel): ?string
    {
        $rel = str_replace('\\', '/', $rel);
        if ($rel === '' || strpos($rel, "\0") !== false) return null;
        $base = realpath($dir);
        if ($base === false) return null;
        $full = realpath($base . '/' . $rel);
        if ($full === false || !is_file($full)) return null;
        if (strpos($full, $base . DIRECTORY_SEPARATOR) !== 0) return null;
        foreach (explode(DIRECTORY_SEPARATOR, substr($full, strlen($base) + 1)) as $part) {
            if ($part !== '' && $part[0] === '.') return null;
        }
        return $full;
    }

    /**
     * 현재 DB 의 모든 테이블(BASE TABLE)을 INSERT 문으로 $file 에 저장
     *  - $withStructure : true 이면 CREATE TABLE IF NOT EXISTS 문도 함께 저장
     *  - 메모리를 아끼기 위해 행을 한 줄씩 읽어 100행씩 묶어서 INSERT 문을 만든다.
     *  - 반환 : ['tables' => 테이블 수, 'rows' => 총 행 수]
     */
    function bk_dump_database(PDO $pdo, string $file, bool $withStructure): array
    {
        $tmp = $file . '.tmp';
        $fh = fopen($tmp, 'wb');
        if ($fh === false) {
            throw new RuntimeException('백업 파일을 만들 수 없습니다. 서버의 쓰기 권한을 확인해 주세요.');
        }

        $tableCount = 0;
        $rowCount   = 0;

        try {
            $dbName  = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();

            fwrite($fh, "-- ------------------------------------------------------------\n");
            fwrite($fh, "-- DB 백업 (INSERT 문)\n");
            fwrite($fh, "-- Database : {$dbName}\n");
            fwrite($fh, "-- Server    : {$version}\n");
            fwrite($fh, "-- 백업 일시 : " . date('Y-m-d H:i:s') . "\n");
            fwrite($fh, "-- ------------------------------------------------------------\n\n");
            fwrite($fh, "SET NAMES utf8mb4;\n");
            fwrite($fh, "SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;\n");
            fwrite($fh, "SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;\n");
            fwrite($fh, "SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

            foreach ($tables as $t) {
                $table = (string)$t[0];
                $q = bk_quote_ident($table);
                $tableCount++;

                // 컬럼 정보 (생성 컬럼은 INSERT 대상에서 제외, 숫자형 컬럼은 따옴표 없이 출력)
                $names   = [];
                $numeric = [];
                foreach ($pdo->query("SHOW COLUMNS FROM {$q}")->fetchAll() as $c) {
                    if (stripos((string)$c['Extra'], 'GENERATED') !== false) continue;
                    $names[]   = (string)$c['Field'];
                    $numeric[] = (bool)preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real)\b/i', (string)$c['Type']);
                }

                $total = (int)$pdo->query("SELECT COUNT(*) FROM {$q}")->fetchColumn();
                fwrite($fh, "-- ------------------------------------------------------------\n");
                fwrite($fh, "-- 테이블 : {$table}  ({$total} 건)\n");
                fwrite($fh, "-- ------------------------------------------------------------\n");

                if ($withStructure) {
                    $create = $pdo->query("SHOW CREATE TABLE {$q}")->fetch(PDO::FETCH_NUM);
                    if ($create && isset($create[1])) {
                        $ddl = (string)preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', (string)$create[1], 1);
                        fwrite($fh, $ddl . ";\n\n");
                    }
                }

                if ($total === 0 || count($names) === 0) {
                    fwrite($fh, "\n");
                    continue;
                }

                $colList = implode(', ', array_map('bk_quote_ident', $names));
                $prefix  = "INSERT INTO {$q} ({$colList}) VALUES\n";

                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);   // 행을 한 줄씩 읽어 메모리 절약
                try {
                    $stmt  = $pdo->query('SELECT ' . $colList . ' FROM ' . $q);
                    $batch = [];
                    while (($r = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
                        $vals = [];
                        foreach ($r as $i => $v) {
                            if ($v === null) {
                                $vals[] = 'NULL';
                            } elseif ($numeric[$i] && preg_match('/^-?\d+(\.\d+)?$/', (string)$v)) {
                                $vals[] = (string)$v;
                            } else {
                                $vals[] = $pdo->quote((string)$v);
                            }
                        }
                        $batch[] = '(' . implode(', ', $vals) . ')';
                        $rowCount++;

                        if (count($batch) >= 100) {
                            fwrite($fh, $prefix . implode(",\n", $batch) . ";\n");
                            $batch = [];
                        }
                    }
                    $stmt->closeCursor();
                    if (count($batch) > 0) {
                        fwrite($fh, $prefix . implode(",\n", $batch) . ";\n");
                    }
                } finally {
                    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                }
                fwrite($fh, "\n");
            }

            fwrite($fh, "SET SQL_MODE=@OLD_SQL_MODE;\n");
            fwrite($fh, "SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;\n");
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;\n");
            fwrite($fh, "-- 백업 끝\n");
        } catch (Throwable $e) {
            fclose($fh);
            @unlink($tmp);
            throw $e;
        }

        fclose($fh);
        if (!rename($tmp, $file)) {      // 성공했을 때만 오늘 파일을 교체
            @unlink($tmp);
            throw new RuntimeException('백업 파일을 저장하지 못했습니다.');
        }
        return ['tables' => $tableCount, 'rows' => $rowCount];
    }
}

$bkAction = $_GET['action'] ?? ($_POST['action'] ?? '');
$bkTodayName = date('Y-m-d') . '.sql';

/* --------------------------------------------------
 * 파일 다운로드
 * -------------------------------------------------- */
if ($bkAction === 'download') {
    $full = bk_resolve_download($bkDir, (string)($_GET['file'] ?? ''));
    if ($full === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo '파일을 찾을 수 없습니다.';
        exit;
    }
    $dlName = basename($full);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($full));
    header('Content-Disposition: attachment; filename="' . rawurlencode($dlName) . '"; filename*=UTF-8\'\'' . rawurlencode($dlName));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    readfile($full);
    exit;
}

/* --------------------------------------------------
 * 백업 파일 목록 (JSON)
 * -------------------------------------------------- */
if ($bkAction === 'list') {
    bk_json([
        'success'    => true,
        'files'      => bk_list_files($bkDir),
        'today_name' => $bkTodayName
    ]);
}

/* --------------------------------------------------
 * 백업 실행 (JSON)
 * -------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bkAction === 'backup') {
    $lock = null;
    try {
        bk_prepare_dir($bkDir);

        // 동시에 두 번 실행되지 않도록 잠금
        $lock = fopen($bkDir . '/.backup.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('다른 백업이 진행 중입니다. 잠시 후 다시 시도해 주세요.');
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        $withStructure = (($_POST['with_structure'] ?? '0') === '1');
        $file = $bkDir . '/' . $bkTodayName;
        $info = bk_dump_database($pdo, $file, $withStructure);
        $size = (int)filesize($file);

        bk_json([
            'success' => true,
            'message' => '백업이 완료되었습니다.',
            'file'    => $bkTodayName,
            'size'    => bk_format_size($size),
            'tables'  => $info['tables'],
            'rows'    => $info['rows']
        ]);
    } catch (PDOException $e) {
        // PDOException 은 RuntimeException 의 하위 클래스이므로 먼저 처리 (DB 오류 상세를 화면에 노출하지 않음)
        error_log('[dbbackup] ' . $e->getMessage());
        bk_json(['success' => false, 'message' => '백업 중 DB 오류가 발생했습니다. 서버 로그를 확인해 주세요.'], 500);
    } catch (RuntimeException $e) {
        bk_json(['success' => false, 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        error_log('[dbbackup] ' . $e->getMessage());
        bk_json(['success' => false, 'message' => '백업 중 오류가 발생했습니다. 서버 로그를 확인해 주세요.'], 500);
    } finally {
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
?>

<style>
    .bk-wrap { width: 100%; background: #fff; }
    .bk-title { margin: 0 0 10px; font-size: 16px; font-weight: 700; color: #2c3e50; text-align: center; }
    .bk-box { padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fafafa; margin-bottom: 16px; }
    .bk-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .bk-btn { height: 38px; padding: 0 18px; border: none; border-radius: 6px; background: #2c3e50; color: #fff; font-size: 14px; font-weight: bold; cursor: pointer; }
    .bk-btn:disabled { opacity: 0.5; cursor: default; }
    .bk-opt { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #555; }
    .bk-msg { min-height: 18px; margin-top: 10px; font-size: 13px; color: #2e7d32; line-height: 1.5; word-break: break-all; }
    .bk-msg.error { color: #c62828; }
    .bk-sub { margin-top: 6px; font-size: 11px; color: #888; line-height: 1.5; }
    .bk-list-title { display: flex; justify-content: space-between; align-items: center; margin: 0 0 6px; font-size: 14px; font-weight: bold; color: #333; }
    .bk-list-title span { font-size: 12px; font-weight: normal; color: #888; }
    .bk-table-wrap { border-top: 1px solid #d1c4e9; border-left: 1px solid #d1c4e9; overflow-x: auto; }
    .bk-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12px; }
    .bk-table th { background: #f3e5f5; color: #4a148c; padding: 6px 4px; border-width: 0 1px 1px 0; border-style: solid; border-color: #d1c4e9; white-space: nowrap; }
    .bk-table td { padding: 7px 6px; border-width: 0 1px 1px 0; border-style: solid; border-color: #e5e5e5; vertical-align: middle; }
    .bk-table td.name { word-break: break-all; }
    .bk-table td.name small { display: block; margin-top: 2px; color: #888; font-size: 11px; }
    .bk-table td.size { white-space: nowrap; text-align: right; }
    .bk-table td.center { text-align: center; }
    .bk-table td.empty { text-align: center; color: #999; padding: 24px 0; }
    .bk-table tr.today td { background: #fffde7; }
    .bk-dl { display: inline-block; padding: 5px 10px; border-radius: 5px; background: #1976d2; color: #fff; font-size: 12px; font-weight: bold; text-decoration: none; white-space: nowrap; }
    .bk-dl:active { background: #1259a6; }
</style>

<div class="bk-wrap">
    <h2 class="bk-title">🗄️ DB 백업</h2>

    <div class="bk-box">
        <div class="bk-actions">
            <button type="button" class="bk-btn" id="bkRunBtn" onclick="bkRun()">💾 지금 백업</button>
            <label class="bk-opt"><input type="checkbox" id="bkStructure"> 테이블 구조(CREATE TABLE) 포함</label>
        </div>
        <div class="bk-msg" id="bkMsg"></div>
        <div class="bk-sub">
            모든 테이블의 데이터를 INSERT 문으로 <b>./db/<?php echo htmlspecialchars($bkTodayName, ENT_QUOTES, 'UTF-8'); ?></b> 에 저장합니다.
            같은 날 다시 백업하면 오늘 파일이 새 내용으로 교체됩니다.
        </div>
    </div>

    <div class="bk-list-title">백업 파일 목록 (./db/) <span id="bkCount"></span></div>
    <div class="bk-table-wrap">
        <table class="bk-table">
            <colgroup><col style="width: 50%;"><col style="width: 22%;"><col style="width: 28%;"></colgroup>
            <thead>
                <tr><th>파일</th><th>크기</th><th>다운로드</th></tr>
            </thead>
            <tbody id="bkBody"></tbody>
        </table>
    </div>
</div>

<script>
    // ※ 화면을 다시 불러와도 스크립트가 재실행되므로 전역 변수는 let/const 가 아닌 var 로 선언
    var bkUrl = <?php echo json_encode($_SERVER['SCRIPT_NAME'], JSON_UNESCAPED_UNICODE); ?>;
    var bkTodayName = <?php echo json_encode($bkTodayName, JSON_UNESCAPED_UNICODE); ?>;
    var bkFiles = [];
    var bkRunning = false;

    function bkEsc(v) {
        return String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function bkMsg(text, isError) {
        var el = document.getElementById('bkMsg');
        el.innerText = text || '';
        el.className = 'bk-msg' + (isError ? ' error' : '');
    }

    // 백업 파일 목록 조회
    function bkLoad() {
        var body = document.getElementById('bkBody');
        fetch(bkUrl + '?action=list', { cache: 'no-store' })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) { body.innerHTML = '<tr><td class="empty" colspan="3">' + bkEsc(res.message || '목록을 불러오지 못했습니다.') + '</td></tr>'; return; }
            bkFiles = res.files || [];
            bkTodayName = res.today_name || bkTodayName;
            document.getElementById('bkCount').innerText = '총 ' + bkFiles.length + '개';

            if (bkFiles.length === 0) {
                body.innerHTML = '<tr><td class="empty" colspan="3">백업 파일이 없습니다.</td></tr>';
                return;
            }
            body.innerHTML = bkFiles.map(function (f) {
                return '<tr class="' + (f.name === bkTodayName ? 'today' : '') + '">'
                    + '<td class="name">' + bkEsc(f.name) + '<small>' + bkEsc(f.mtime_text) + '</small></td>'
                    + '<td class="size">' + bkEsc(f.size_text) + '</td>'
                    + '<td class="center"><a class="bk-dl" href="' + bkUrl + '?action=download&file=' + encodeURIComponent(f.name) + '">⬇ 다운로드</a></td>'
                    + '</tr>';
            }).join('');
        })
        .catch(function () {
            body.innerHTML = '<tr><td class="empty" colspan="3">목록을 불러오지 못했습니다.</td></tr>';
        });
    }

    // 백업 실행
    function bkRun() {
        if (bkRunning) return;

        var exists = bkFiles.some(function (f) { return f.name === bkTodayName; });
        if (exists && !confirm('오늘(' + bkTodayName + ') 백업 파일이 이미 있어요. 새 내용으로 교체할까요?')) return;

        var fd = new FormData();
        fd.append('action', 'backup');
        fd.append('with_structure', document.getElementById('bkStructure').checked ? '1' : '0');

        bkRunning = true;
        var btn = document.getElementById('bkRunBtn');
        btn.disabled = true;
        btn.innerText = '⏳ 백업 중...';
        bkMsg('백업 중입니다. 데이터가 많으면 시간이 걸릴 수 있어요.');

        fetch(bkUrl, { method: 'POST', body: fd })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) { bkMsg(res.message || '백업에 실패했습니다.', true); return; }
            bkMsg('✅ ' + res.message + ' (' + res.file + ' · 테이블 ' + res.tables + '개 · ' + Number(res.rows).toLocaleString() + '건 · ' + res.size + ')');
            bkLoad();
        })
        .catch(function () { bkMsg('통신 오류로 백업에 실패했습니다. 서버 시간 제한을 확인해 주세요.', true); })
        .finally(function () {
            bkRunning = false;
            btn.disabled = false;
            btn.innerText = '💾 지금 백업';
        });
    }

    bkLoad();
</script>