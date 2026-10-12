<!-- 품목/수량 변경 팝업 모달 -->
<div class="shipping-modal-overlay" id="productChangeModal" style="display:none;">
    <div class="shipping-modal-content">
        <h3>품목 및 수량 변경</h3>
        <div class="shipping-form-group">
            <label for="change-product-type">상품 종류</label>
            <select id="change-product-type" style="width:100%; height:30px; border:1px solid #ddd; border-radius:4px; font-size:12px;">
                <option value="찹찹">찹찹</option>
                <option value="바찹">바찹</option>
                <option value="바라">바라</option>
                <option value="옆순">옆순</option>
            </select>
        </div>
        <div class="shipping-form-group">
            <label for="change-qty">수량</label>
            <input type="text" id="change-qty" placeholder="숫자만 입력" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        </div>
        <div class="shipping-form-group">
            <label for="change-disposal">폐기</label>
            <input type="text" id="change-disposal" placeholder="숫자만 입력" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        </div>
        <div class="shipping-modal-actions">
            <button type="button" class="btn-cancel" onclick="document.getElementById('productChangeModal').style.display='none';">닫기</button>
            <button type="button" class="btn-save" onclick="saveProductChangeRow()">확인</button>
        </div>
    </div>
</div>