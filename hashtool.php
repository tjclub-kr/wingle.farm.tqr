<?php
require_once __DIR__ . '/auth_check.php';   // 로그인 안 된 상태면 접근 불가

/* ---------- 변환 요청 처리 (AJAX POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'hash') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');

    $plain = (string)($_POST['plain'] ?? '');

    if ($plain === '') {
        http_response_code(400);
        echo json_encode(['error' => '변환할 문자열을 입력해 주세요.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // bcrypt 는 72바이트까지만 반영하므로 초과 시 거부
    if (strlen($plain) > 72) {
        http_response_code(400);
        echo json_encode(['error' => '72바이트(영문 기준 72자)를 넘을 수 없습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['hash' => password_hash($plain, PASSWORD_DEFAULT)], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!-- hashtool.php : 평문 → 해시 변환 (index.php 의 #content-area 안에 삽입되는 조각 화면) -->
<div id="hashtool-box" style="max-width:360px; margin:0 auto;">
    <h3 style="font-size:18px; color:#2c3e50; text-align:center; margin-bottom:16px;">해시 변환</h3>

    <label for="hashtool-plain" style="display:block; font-size:14px; font-weight:bold; color:#555; margin-bottom:5px;">평문</label>
    <input type="password" id="hashtool-plain" autocomplete="off" autocapitalize="off" spellcheck="false"
           style="width:100%; padding:11px; border:1px solid #ddd; border-radius:6px; font-size:16px; font-family:inherit;">

    <label style="display:flex; align-items:center; gap:6px; font-size:13px; color:#555; margin:8px 0 14px;">
        <input type="checkbox" id="hashtool-show"> 입력 내용 보기
    </label>

    <button type="button" id="hashtool-convert"
            style="width:100%; padding:12px; border:none; border-radius:6px; background:#2c3e50; color:#fff; font-size:15px; font-weight:bold; cursor:pointer;">
        변환
    </button>

    <p id="hashtool-msg" style="color:#c0392b; font-size:14px; margin-top:12px; display:none;"></p>

    <div id="hashtool-result-wrap" style="display:none; margin-top:18px;">
        <label for="hashtool-result" style="display:block; font-size:14px; font-weight:bold; color:#555; margin-bottom:5px;">해시 결과</label>
        <textarea id="hashtool-result" rows="3" readonly
                  style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:13px; font-family:monospace; resize:none; word-break:break-all; background:#f8f9fa;"></textarea>
        <button type="button" id="hashtool-copy"
                style="width:100%; margin-top:8px; padding:10px; border:none; border-radius:6px; background:#e0e0e0; color:#333; font-size:14px; font-weight:bold; cursor:pointer;">
            복사
        </button>
        <p style="font-size:12px; color:#888; margin-top:8px;">같은 평문도 변환할 때마다 다른 해시가 나오며, 모두 정상입니다.</p>
    </div>
</div>

<script>
// 화면을 다시 불러와도 변수 충돌이 나지 않도록 즉시 실행 함수로 감쌈
(function () {
    const $ = id => document.getElementById(id);
    const plain = $('hashtool-plain'), msg = $('hashtool-msg');
    const wrap = $('hashtool-result-wrap'), result = $('hashtool-result');
    const btn = $('hashtool-convert');

    function showMsg(text) {
        msg.textContent = text || '';
        msg.style.display = text ? 'block' : 'none';
    }

    $('hashtool-show').addEventListener('change', function () {
        plain.type = this.checked ? 'text' : 'password';
    });

    async function convert() {
        showMsg('');
        wrap.style.display = 'none';
        if (!plain.value) { showMsg('변환할 문자열을 입력해 주세요.'); return; }

        btn.disabled = true;
        btn.textContent = '변환 중...';
        try {
            const body = new URLSearchParams({ plain: plain.value });
            const response = await fetch('hashtool.php?action=hash', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body
            });
            const data = await response.json();
            if (!response.ok || !data.hash) throw new Error(data.error || '변환에 실패했습니다.');
            result.value = data.hash;
            wrap.style.display = 'block';
        } catch (e) {
            showMsg(e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = '변환';
        }
    }

    btn.addEventListener('click', convert);
    plain.addEventListener('keydown', e => { if (e.key === 'Enter') convert(); });

    $('hashtool-copy').addEventListener('click', async function () {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(result.value);
            } else {
                result.select();
                document.execCommand('copy');   // http 접속 등 clipboard API 불가 시 대체
            }
            this.textContent = '복사됨';
        } catch (e) {
            result.select();
            this.textContent = '직접 복사해 주세요';
        }
        setTimeout(() => { this.textContent = '복사'; }, 1500);
    });
})();
</script>
