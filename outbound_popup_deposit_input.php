<!-- 입금액 입력 팝업 모달 -->
<div class="shipping-modal-overlay" id="depositModal">
    <div class="shipping-modal-content">
        <h3>입금액 입력</h3>
        <div class="shipping-form-group">
            <label for="deposit-amount">입금액</label>
            <input type="text" id="deposit-amount" placeholder="숫자만 입력" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        </div>
        <div class="shipping-modal-actions">
            <button type="button" class="btn-cancel" onclick="document.getElementById('depositModal').style.display='none';">닫기</button>
            <button type="button" class="btn-save" onclick="saveDepositRow()">확인</button>
        </div>
    </div>
</div>