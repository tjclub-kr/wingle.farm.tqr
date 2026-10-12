<!-- 출고 정보 입력 메인 팝업 모달 (신규 추가 및 수정 겸용) -->
<div class="shipping-modal-overlay" id="shippingModal">
    <div class="shipping-modal-content">
        <h3 id="shippingModalTitle">출고 정보 입력</h3>
        
        <div class="shipping-top-row" id="shippingTopRowContainer">
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label for="shipping-date">날짜</label>
                <input type="date" id="shipping-date">
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label for="shipping-farm">밭 이름</label>
                <select id="shipping-farm">
                    <option value="대">대</option>
                    <option value="한">한</option>
                    <option value="민">민</option>
                    <option value="국">국</option>
                </select>
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;" id="productCountGroup">
                <label for="shipping-product-count">상품 종류 수</label>
                <div style="display: flex; gap: 4px;">
                    <input type="text" id="shipping-product-count" placeholder="숫자" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                    <button type="button" onclick="generateProductInputs()" style="background:#4a148c; color:#fff; border:none; border-radius:4px; height:30px; padding:0 12px; font-weight:bold; cursor:pointer; white-space:nowrap; flex-shrink:0;">생성</button>
                </div>
            </div>
        </div>

        <div id="dynamicProductList">
        </div>

        <div class="shipping-modal-actions">
            <button type="button" class="btn-cancel" onclick="closeShippingModal()">닫기</button>
            <button type="button" class="btn-save" onclick="saveShippingRow()">확인</button>
        </div>
    </div>
</div>