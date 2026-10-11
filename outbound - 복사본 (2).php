<?php
/**
 * shippingmng.php : 출고 관리 화면 (DB 연동 없이 퍼블리싱 화면 구성)
 */
declare(strict_types=1);
?>
<style>
    .shipping-wrap {
        padding: 5px;
        background: #fff;
        border-radius: 8px;
    }
    .shipping-header-area {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        margin-bottom: 10px;
        width: 100%;
    }
    .shipping-title {
        grid-column: 2;
        font-size: 16px;
        font-weight: bold;
        color: #2c3e50;
        margin: 0;
        text-align: center;
    }
    .header-btn-group {
        grid-column: 3;
        justify-self: flex-end;
        display: flex;
        align-items: center;
        gap: 5px;
        flex-shrink: 0;
    }
    .header-save-btn {
        background-color: #2e7d32;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
        display: none; /* 초기에는 숨김 */
    }
    .add-row-btn {
        background-color: #4a148c;
        color: white;
        border: none;
        border-radius: 4px;
        width: 28px;
        height: 28px;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .shipping-table-container {
        width: 100%;
        overflow-x: auto;
    }
    .shipping-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        text-align: center;
        white-space: nowrap;
    }
    .shipping-table th {
        background-color: #f3e5f5;
        color: #4a148c;
        font-weight: bold;
        padding: 4px 2px;
        border: 1px solid #d1c4e9;
    }
    .shipping-table td {
        padding: 3px 2px;
        border: 1px solid #e0e0e0;
        background-color: #fff;
    }
    /* 일반 실선 적용 */
    .shipping-table tr.group-divider td {
        border-bottom: 1px solid #e0e0e0;
    }
    /* 날짜 아래(날짜별 합계 아랫줄) 일반 실선 적용 */
    .shipping-table tr.section-divider td {
        border-bottom: 1px solid #e0e0e0;
    }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    
    /* 밭별 소계 (병아리색) */
    .farm-subtotal-row td {
        background-color: #fffde7 !important;
        font-weight: bold;
    }
    /* 날짜별 총합 (옅은 보라색) */
    .date-total-row td {
        background-color: #ede7f6 !important;
        font-weight: bold;
    }

    .custom-row td {
        cursor: pointer;
        background-color: #fffde7 !important;
    }
    .custom-row td:hover {
        background-color: #fff9c4 !important;
    }
    .delete-btn {
        background-color: #ffebee;
        color: #d32f2f;
        border: 1px solid #ef9a9a;
        border-radius: 4px;
        padding: 2px 6px;
        font-size: 11px;
        font-weight: bold;
        cursor: pointer;
        display: inline-block;
    }
    .delete-btn:hover {
        background-color: #ffcdd2;
    }

    /* 팝업 모달 스타일 */
    .shipping-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    .shipping-modal-content {
        background: #fff;
        border-radius: 8px;
        width: 92%;
        max-width: 480px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    .shipping-modal-content h3 {
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 16px;
        color: #2c3e50;
        text-align: center;
    }
    
    /* 메인 팝업 상단 기본정보 배치 (날짜, 밭 이름, 상품 수량) */
    .shipping-top-row {
        display: grid;
        grid-template-columns: auto 1fr 1.2fr;
        gap: 8px;
        align-items: end;
        margin-bottom: 15px;
        padding-bottom: 12px;
        border-bottom: 1px solid #eee;
    }
    .shipping-form-group {
        margin-bottom: 10px;
    }
    .shipping-form-group label {
        display: block;
        font-size: 11px;
        font-weight: bold;
        color: #555;
        margin-bottom: 3px;
    }
    .shipping-form-group input[type="date"],
    .shipping-form-group input[type="text"],
    .shipping-form-group select,
    .shipping-form-group textarea {
        width: 100%;
        height: 30px; /* 높이 통일 */
        padding: 4px 6px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
        font-family: inherit;
        box-sizing: border-box;
    }
    /* 날짜 컴포넌트는 연월일 정보가 보이는 컴팩트한 크기로 지정 */
    .shipping-form-group input[type="date"] {
        width: 130px; 
    }
    .shipping-form-group textarea {
        resize: none;
    }

    /* 동적 상품 입력 리스트 영역 스타일 */
    #dynamicProductList {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 250px;
        overflow-y: auto;
        margin-bottom: 15px;
        padding-right: 4px;
    }
    .dynamic-product-item {
        display: grid;
        grid-template-columns: 1.2fr 1fr 1fr;
        gap: 8px;
        background: #fdfbfd;
        padding: 8px;
        border: 1px solid #e1bee7;
        border-radius: 6px;
        align-items: center;
    }
    .dynamic-product-item .shipping-form-group select,
    .dynamic-product-item .shipping-form-group input {
        height: 30px;
    }

    .shipping-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 15px;
    }
    .shipping-modal-actions button {
        padding: 8px 14px;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
    }
    .btn-cancel { background-color: #90a4ae; color: #fff; flex: 1; }
    .btn-save { background-color: #4a148c; color: #fff; flex: 2; }
</style>

<div class="shipping-wrap">
    <div class="shipping-header-area">
        <h2 class="shipping-title">📦 출고 관리</h2>
        <div class="header-btn-group">
            <button type="button" class="header-save-btn" id="headerSaveBtn" onclick="saveNewRowAction()">저장</button>
            <button type="button" class="add-row-btn" onclick="openNewShippingModal()" title="한 줄 추가">+</button>
        </div>
    </div>
    
    <div class="shipping-table-container">
        <table class="shipping-table" id="shippingTable">
            <thead>
                <tr>
                    <th>날짜</th>
                    <th>밭<BR>이름</th>
                    <th>상품<BR>종류</th>
                    <th>수량</th>
                    <th>폐기</th>
                    <th>경매<BR>단가</th>
                    <th>경매<BR>총액</th>
                    <th>입금액</th>
                    <th>입금<BR>단가</th>
                    <th>수수료</th>
                </tr>
            </thead>
            <tbody>
                <!-- 첫 번째 블록: 10월 10일 (대, 한, 민) -->
                <tr data-date="2026-10-10" data-farm="대">
                    <td rowspan="16" style="vertical-align: middle; background-color: #fafafa; border-bottom: 1px solid #e0e0e0;">10<BR>/<BR>10</td>
                    <td rowspan="5" style="vertical-align: middle; background-color: #fafafa;">대</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">0</td>
                    <td class="text-right">10,000</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">19,000</td>
                    <td class="text-right">9,500</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr data-date="2026-10-10" data-farm="대">
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr data-date="2026-10-10" data-farm="대">
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr data-date="2026-10-10" data-farm="대">
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">0</td>
                    <td class="text-right">40,000</td>
                    <td class="text-right">200,000</td>
                    <td class="text-right">190,000</td>
                    <td class="text-right">38,000</td>
                    <td class="text-center">5%</td>
                </tr>
                <!-- 대 밭 소계 (병아리색) -->
                <tr class="farm-subtotal-row group-divider" data-date="2026-10-10" data-farm="대">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <tr data-date="2026-10-10" data-farm="한">
                    <td rowspan="5" style="vertical-align: middle; background-color: #fafafa;">한</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">0</td>
                    <td class="text-right">10,000</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">19,000</td>
                    <td class="text-right">9,500</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr data-date="2026-10-10" data-farm="한">
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr data-date="2026-10-10" data-farm="한">
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr data-date="2026-10-10" data-farm="한">
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">0</td>
                    <td class="text-right">40,000</td>
                    <td class="text-right">200,000</td>
                    <td class="text-right">190,000</td>
                    <td class="text-right">38,000</td>
                    <td class="text-center">5%</td>
                </tr>
                <!-- 한 밭 소계 (병아리색) -->
                <tr class="farm-subtotal-row group-divider" data-date="2026-10-10" data-farm="한">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <tr data-date="2026-10-10" data-farm="민">
                    <td rowspan="5" style="vertical-align: middle; background-color: #fafafa;">민</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">0</td>
                    <td class="text-right">10,000</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">19,000</td>
                    <td class="text-right">9,500</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr data-date="2026-10-10" data-farm="민">
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr data-date="2026-10-10" data-farm="민">
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr data-date="2026-10-10" data-farm="민">
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">0</td>
                    <td class="text-right">40,000</td>
                    <td class="text-right">200,000</td>
                    <td class="text-right">190,000</td>
                    <td class="text-right">38,000</td>
                    <td class="text-center">5%</td>
                </tr>
                <!-- 민 밭 소계 (병아리색) -->
                <tr class="farm-subtotal-row group-divider" data-date="2026-10-10" data-farm="민">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <!-- 10/10 날짜 총합 -->
                <tr class="date-total-row section-divider" data-date="2026-10-10">
                    <td>합계</td>
                    <td>-</td>
                    <td class="text-right">42</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">1,200,000</td>
                    <td class="text-right">1,134,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>

                <!-- 두 번째 블록: 10월 09일 (대, 한) -->
				<tr data-date="2026-10-09" data-farm="대">
                    <td rowspan="11" style="vertical-align: middle; background-color: #fafafa; border-bottom: 1px solid #e0e0e0;">10<BR>/<BR>09</td>
                    <td rowspan="5" style="vertical-align: middle; background-color: #fafafa;">대</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">0</td>
                    <td class="text-right">10,000</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">19,000</td>
                    <td class="text-right">9,500</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr data-date="2026-10-09" data-farm="대">
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr data-date="2026-10-09" data-farm="대">
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr data-date="2026-10-09" data-farm="대">
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">0</td>
                    <td class="text-right">40,000</td>
                    <td class="text-right">200,000</td>
                    <td class="text-right">190,000</td>
                    <td class="text-right">38,000</td>
                    <td class="text-center">5%</td>
                </tr>
                <!-- 대 밭 소계 (병아리색) -->
                <tr class="farm-subtotal-row group-divider" data-date="2026-10-09" data-farm="대">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <tr data-date="2026-10-09" data-farm="한">
                    <td rowspan="5" style="vertical-align: middle; background-color: #fafafa;">한</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">0</td>
                    <td class="text-right">10,000</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">19,000</td>
                    <td class="text-right">9,500</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr data-date="2026-10-09" data-farm="한">
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr data-date="2026-10-09" data-farm="한">
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr data-date="2026-10-09" data-farm="한">
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">0</td>
                    <td class="text-right">40,000</td>
                    <td class="text-right">200,000</td>
                    <td class="text-right">190,000</td>
                    <td class="text-right">38,000</td>
                    <td class="text-center">5%</td>
                </tr>
                <!-- 한 밭 소계 (병아리색) -->
                <tr class="farm-subtotal-row group-divider" data-date="2026-10-09" data-farm="한">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <!-- 10/09 날짜 총합 -->
                <tr class="date-total-row section-divider" data-date="2026-10-09">
                    <td>합계</td>
                    <td>-</td>
                    <td class="text-right">28</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">800,000</td>
                    <td class="text-right">756,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>

                <!-- 세 번째 블록: 10월 08일 (민, 국) -->
				<tr data-date="2026-10-08" data-farm="민">
                    <td rowspan="11" style="vertical-align: middle; background-color: #fafafa; border-bottom: 1px solid #e0e0e0;">10<BR>/<BR>08</td>
                    <td rowspan="5" style="vertical-align: middle; background-color: #fafafa;">민</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">0</td>
                    <td class="text-right">10,000</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">19,000</td>
                    <td class="text-right">9,500</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr data-date="2026-10-08" data-farm="민">
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr data-date="2026-10-08" data-farm="민">
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr data-date="2026-10-08" data-farm="민">
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">0</td>
                    <td class="text-right">40,000</td>
                    <td class="text-right">200,000</td>
                    <td class="text-right">190,000</td>
                    <td class="text-right">38,000</td>
                    <td class="text-center">5%</td>
                </tr>
                <!-- 민 밭 소계 (병아리색) -->
                <tr class="farm-subtotal-row group-divider" data-date="2026-10-08" data-farm="민">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <tr data-date="2026-10-08" data-farm="국">
                    <td rowspan="5" style="vertical-align: middle; background-color: #fafafa;">국</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">0</td>
                    <td class="text-right">10,000</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">19,000</td>
                    <td class="text-right">9,500</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr data-date="2026-10-08" data-farm="국">
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr data-date="2026-10-08" data-farm="국">
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr data-date="2026-10-08" data-farm="국">
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">0</td>
                    <td class="text-right">40,000</td>
                    <td class="text-right">200,000</td>
                    <td class="text-right">190,000</td>
                    <td class="text-right">38,000</td>
                    <td class="text-center">5%</td>
                </tr>
                <!-- 국 밭 소계 (병아리색) -->
                <tr class="farm-subtotal-row group-divider" data-date="2026-10-08" data-farm="국">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <!-- 10/08 날짜 총합 -->
                <tr class="date-total-row section-divider" data-date="2026-10-08">
                    <td>합계</td>
                    <td>-</td>
                    <td class="text-right">28</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">800,000</td>
                    <td class="text-right">756,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 출고 정보 입력 메인 팝업 모달 (신규 추가 및 수정 겸용) -->
<div class="shipping-modal-overlay" id="shippingModal">
    <div class="shipping-modal-content">
        <h3 id="shippingModalTitle">출고 정보 입력</h3>
        
        <!-- 상단: 날짜, 밭 이름, 상품 수량 및 확인 버튼 -->
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

        <!-- 상품 수량만큼 동적으로 생성될 입력 리스트 영역 -->
        <div id="dynamicProductList">
            <!-- 동적 생성 항목들이 여기에 들어감 -->
        </div>

        <div class="shipping-modal-actions">
            <button type="button" class="btn-cancel" onclick="closeShippingModal()">닫기</button>
            <button type="button" class="btn-save" onclick="saveShippingRow()">확인</button>
        </div>
    </div>
</div>

<!-- 경매 단가 / 경매 총액 입력 팝업 모달 -->
<div class="shipping-modal-overlay" id="auctionModal">
    <div class="shipping-modal-content">
        <h3>경매 정보 입력</h3>
        <div class="shipping-form-group">
            <label for="auction-unit">경매 단가</label>
            <input type="text" id="auction-unit" placeholder="단가 입력" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        </div>
        <div class="shipping-form-group">
            <label for="auction-total">경매 총액</label>
            <input type="text" id="auction-total" placeholder="숫자만 입력" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        </div>
        <div class="shipping-modal-actions">
            <button type="button" class="btn-cancel" onclick="document.getElementById('auctionModal').style.display='none';">닫기</button>
            <button type="button" class="btn-save" onclick="saveAuctionRow()">확인</button>
        </div>
    </div>
</div>

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

<script>
let currentEditingRow = null;

// 숫자 콤마 포맷 및 소수점 절삭 헬퍼 함수
function formatNum(val) {
    if (val === '-' || val === '' || isNaN(val)) return '-';
    return Math.floor(Number(val)).toLocaleString();
}

// '+' 버튼 클릭 시 메인 팝업 열기 (신규 추가 모드)
function openNewShippingModal() {
    currentEditingRow = null; 
    document.getElementById('shippingModalTitle').innerText = '출고 정보 입력';
    document.getElementById('shippingTopRowContainer').style.display = 'grid';
    
    const todayStr = new Date().toISOString().split('T')[0];
    document.getElementById('shipping-date').value = todayStr;
    document.getElementById('shipping-farm').value = '대';
    document.getElementById('shipping-product-count').value = '1';
    
    generateProductInputs(1);
    document.getElementById('shippingModal').style.display = 'flex';
}

// 상품 종류 수 확인 버튼 클릭 시 수량만큼 입력 필드 동적 생성
function generateProductInputs(defaultCount = null) {
    const countInput = document.getElementById('shipping-product-count');
    const count = defaultCount !== null ? defaultCount : parseInt(countInput.value) || 0;
    const container = document.getElementById('dynamicProductList');
    container.innerHTML = '';

    if (count <= 0) {
        container.innerHTML = '<div style="text-align:center; color:#888; font-size:12px; padding:15px;">상품 종류 수를 입력하고 생성 버튼을 눌러줘</div>';
        return;
    }

    for (let i = 0; i < count; i++) {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'dynamic-product-item';
        itemDiv.innerHTML = `
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>상품 종류</label>
                <select class="dyn-product-select">
                    <option value="찹찹">찹찹</option>
                    <option value="바찹">바찹</option>
                    <option value="바라">바라</option>
                    <option value="옆순">옆순</option>
                </select>
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>수량</label>
                <input type="text" class="dyn-product-qty" placeholder="숫자만" value="1" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>폐기</label>
                <input type="text" class="dyn-product-disposal" placeholder="숫자만" value="0" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            </div>
        `;
        container.appendChild(itemDiv);
    }
}

// 기존 Row 수정용 팝업 열기 조건 검사 후 오픈
function checkAndOpenEdit(row) {
    const isNewRow = row.dataset.isNew === 'true';
    const auctionUnit = parseFloat(row.dataset.auctionUnit) || 0;

    // New Row가 아니고 경매단가 항목이 0이 아니면(1 이상이면) 수정 팝업 오픈 차단
    if (!isNewRow && auctionUnit !== 0) {
        alert('경매단가가 입력된 행은 상품 종류나 수량을 수정할 수 없어!');
        return;
    }

    openEditShippingModal(row);
}

function openEditShippingModal(row) {
    currentEditingRow = row;
    document.getElementById('shippingModalTitle').innerText = '출고 정보 수정';
    
    document.getElementById('shippingTopRowContainer').style.display = 'none';
    
    const container = document.getElementById('dynamicProductList');
    container.innerHTML = `
        <div class="shipping-form-group">
            <label>날짜</label>
            <input type="date" id="edit-single-date" value="${row.dataset.date || ''}">
        </div>
        <div class="shipping-form-group">
            <label>밭 이름</label>
            <select id="edit-single-farm">
                <option value="대" ${row.dataset.farm==='대'?'selected':''}>대</option>
                <option value="한" ${row.dataset.farm==='한'?'selected':''}>한</option>
                <option value="민" ${row.dataset.farm==='민'?'selected':''}>민</option>
                <option value="국" ${row.dataset.farm==='국'?'selected':''}>국</option>
            </select>
        </div>
        <div class="dynamic-product-item" style="grid-template-columns: 1.2fr 1fr 1fr;">
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>상품 종류</label>
                <select id="edit-single-product" class="dyn-product-select">
                    <option value="찹찹" ${row.dataset.product==='찹찹'?'selected':''}>찹찹</option>
                    <option value="바찹" ${row.dataset.product==='바찹'?'selected':''}>바찹</option>
                    <option value="바라" ${row.dataset.product==='바라'?'selected':''}>바라</option>
                    <option value="옆순" ${row.dataset.product==='옆순'?'selected':''}>옆순</option>
                </select>
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>수량</label>
                <input type="text" id="edit-single-qty" class="dyn-product-qty" value="${row.dataset.qty || row.cells[3].innerText}" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>폐기</label>
                <input type="text" id="edit-single-disposal" class="dyn-product-disposal" value="${row.dataset.disposal || row.cells[4].innerText}" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            </div>
        </div>
    `;

    document.getElementById('shippingModal').style.display = 'flex';
}

// 메인 팝업 닫기
function closeShippingModal() {
    document.getElementById('shippingModal').style.display = 'none';
    currentEditingRow = null;
}

// 메인 팝업 확인 버튼 (신규 추가 또는 기존 행 수정 반영 및 저장 버튼 표시)
function saveShippingRow() {
    if (currentEditingRow) {
        // 기존 행 수정 모드
        const dateVal = document.getElementById('edit-single-date').value;
        const farmVal = document.getElementById('edit-single-farm').value;
        const productVal = document.getElementById('edit-single-product').value;
        const qtyVal = document.getElementById('edit-single-qty').value || '0';
        const disposalVal = document.getElementById('edit-single-disposal').value || '0';

        if (!dateVal) {
            alert('날짜를 입력해 줘!');
            return;
        }

        currentEditingRow.dataset.date = dateVal;
        currentEditingRow.dataset.farm = farmVal;
        currentEditingRow.dataset.product = productVal;
        currentEditingRow.dataset.qty = qtyVal;
        currentEditingRow.dataset.disposal = disposalVal;

        const dateFormatted = dateVal.substring(5, 7) + '/' + dateVal.substring(8, 10);
        currentEditingRow.cells[0].innerText = dateFormatted;
        currentEditingRow.cells[1].innerText = farmVal;
        currentEditingRow.cells[2].innerText = productVal;
        currentEditingRow.cells[3].innerText = qtyVal;
        currentEditingRow.cells[4].innerText = disposalVal;

        // 수정 시에도 저장 버튼 표시
        document.getElementById('headerSaveBtn').style.display = 'inline-block';
        closeShippingModal();

    } else {
        // 신규 추가 모드
        const dateVal = document.getElementById('shipping-date').value;
        const farmVal = document.getElementById('shipping-farm').value;
        
        if (!dateVal) {
            alert('날짜를 입력해 줘!');
            return;
        }

        const items = document.querySelectorAll('.dynamic-product-item');
        if (items.length === 0) {
            alert('상품 종류 수를 입력하고 생성해 줘!');
            return;
        }

        const tableBody = document.querySelector('#shippingTable tbody');
        const dateFormatted = dateVal.substring(5, 7) + '/' + dateVal.substring(8, 10);

        items.forEach((item) => {
            const productVal = item.querySelector('.dyn-product-select').value;
            const qtyVal = item.querySelector('.dyn-product-qty').value || '0';
            const disposalVal = item.querySelector('.dyn-product-disposal').value || '0';

            const newRow = document.createElement('tr');
            newRow.className = 'custom-row';
            newRow.dataset.date = dateVal;
            newRow.dataset.farm = farmVal;
            newRow.dataset.product = productVal;
            newRow.dataset.isNew = 'true'; // New Row 표시 플래그
            newRow.dataset.qty = qtyVal;
            newRow.dataset.auctionUnit = '0';
            newRow.dataset.auctionTotal = '0';
            newRow.dataset.depositAmount = '0';

            newRow.innerHTML = `
                <td style="background-color: #fffde7;">${dateFormatted}</td>
                <td style="background-color: #fffde7;">${farmVal}</td>
                <td style="background-color: #fffde7;">${productVal}</td>
                <td style="background-color: #fffde7;" class="text-right">${qtyVal}</td>
                <td style="background-color: #fffde7;" class="text-right">${disposalVal}</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-center"><button type="button" class="delete-btn">-</button></td>
            `;

            setupRowEvents(newRow);
            tableBody.insertBefore(newRow, tableBody.firstChild);
        });

        document.getElementById('headerSaveBtn').style.display = 'inline-block';
        closeShippingModal();
    }
}

// 생성된 행에 이벤트 바인딩 및 조건별 팝업 오픈 제어
function setupRowEvents(row) {
    // 1. 날짜, 밭 이름, 상품 종류, 수량, 폐기 클릭 시 수정 팝업 열기
    for (let i = 0; i < 5; i++) {
        row.cells[i].onclick = (e) => {
            e.stopPropagation();
            checkAndOpenEdit(row);
        };
    }

    // 2. 경매단가 / 경매총액 클릭 이벤트 제어
    row.cells[5].onclick = (e) => { e.stopPropagation(); checkAndOpenAuction(row); };
    row.cells[6].onclick = (e) => { e.stopPropagation(); checkAndOpenAuction(row); };

    // 3. 입금액 / 입금단가 클릭 이벤트 제어
    row.cells[7].onclick = (e) => { e.stopPropagation(); checkAndOpenDeposit(row); };
    row.cells[8].onclick = (e) => { e.stopPropagation(); checkAndOpenDeposit(row); };

    // 4. 삭제 버튼
    const deleteBtn = row.querySelector('.delete-btn');
    if (deleteBtn) {
        deleteBtn.onclick = (e) => {
            e.stopPropagation();
            if (confirm('이 줄을 삭제할까?')) {
                row.remove();
                checkHeaderSaveButton();
            }
        };
    }
}

// 경매 단가 팝업 열기 조건 검사
function checkAndOpenAuction(row) {
    const isNewRow = row.dataset.isNew === 'true';
    const depositAmount = parseFloat(row.dataset.depositAmount) || 0;
    const qty = parseFloat(row.dataset.qty || row.cells[3].innerText) || 0;

    // New Row가 아니고 입금액이 0이 아니면(1 이상이면) 경매단가/총액 수정 팝업 오픈 차단
    if (!isNewRow && depositAmount !== 0) {
        alert('입금액이 입력된 행은 경매단가를 수정할 수 없어!');
        return;
    }
    if (isNewRow) {
        alert('New Row에서는 경매 단가를 입력할 수 없어!');
        return;
    }
    if (qty < 1) {
        alert('수량이 1 이상이어야 경매 단가를 입력할 수 있어!');
        return;
    }

    currentEditingRow = row;
    const currentUnit = row.dataset.auctionUnit ? Number(row.dataset.auctionUnit) / 100 : '';
    document.getElementById('auction-unit').value = currentUnit !== 0 ? currentUnit : '';
    document.getElementById('auction-total').value = row.dataset.auctionTotal || '';
    document.getElementById('auctionModal').style.display = 'flex';
}

// 경매 단가 팝업 저장
function saveAuctionRow() {
    if (!currentEditingRow) return;

    const rawUnit = parseFloat(document.getElementById('auction-unit').value) || 0;
    const unitVal = rawUnit * 100;
    const qty = parseFloat(currentEditingRow.dataset.qty || currentEditingRow.cells[3].innerText) || 1;
    const totalVal = unitVal * qty;

    currentEditingRow.dataset.auctionUnit = unitVal;
    currentEditingRow.dataset.auctionTotal = totalVal;

    currentEditingRow.cells[5].innerText = unitVal !== 0 ? formatNum(unitVal) : '-';
    currentEditingRow.cells[6].innerText = totalVal !== 0 ? formatNum(totalVal) : '-';

    document.getElementById('headerSaveBtn').style.display = 'inline-block';
    document.getElementById('auctionModal').style.display = 'none';
    currentEditingRow = null;
}

// 입금액 팝업 열기 조건 검사
function checkAndOpenDeposit(row) {
    const isNewRow = row.dataset.isNew === 'true';
    const depositAmount = parseFloat(row.dataset.depositAmount) || 0;

    if (isNewRow) {
        alert('New Row에서는 입금액을 입력할 수 없어!');
        return;
    }

    currentEditingRow = row;
    // 입금액이 0이면 신규 입력, 0이 아니면 기존 입력값 세팅 가능
    document.getElementById('deposit-amount').value = depositAmount !== 0 ? depositAmount / 100 : '';
    document.getElementById('depositModal').style.display = 'flex';
}

// 입금액 팝업 저장
function saveDepositRow() {
    if (!currentEditingRow) return;

    const rawDeposit = parseFloat(document.getElementById('deposit-amount').value) || 0;
    const depositVal = rawDeposit * 100;
    const qty = parseFloat(currentEditingRow.dataset.qty || currentEditingRow.cells[3].innerText) || 1;
    const depositUnit = qty > 0 ? (depositVal / qty) : 0;

    currentEditingRow.dataset.depositAmount = depositVal;
    currentEditingRow.dataset.depositUnit = depositUnit;

    currentEditingRow.cells[7].innerText = depositVal !== 0 ? formatNum(depositVal) : '-';
    currentEditingRow.cells[8].innerText = depositUnit !== 0 ? formatNum(depositUnit) : '-';

    document.getElementById('headerSaveBtn').style.display = 'inline-block';
    document.getElementById('depositModal').style.display = 'none';
    currentEditingRow = null;
}

// 헤더의 "저장" 버튼 클릭 시 (모든 변경된 정보 및 New Row 정보 저장 처리)
function saveNewRowAction() {
    if (confirm('작성 및 수정된 모든 내용을 저장할까?')) {
        document.querySelectorAll('#shippingTable tbody tr.custom-row').forEach(row => {
            row.dataset.isNew = 'false';
            for (let i = 0; i < 5; i++) {
                if (row.cells[i]) row.cells[i].style.backgroundColor = '#fff';
            }
        });
        document.getElementById('headerSaveBtn').style.display = 'none';
        alert('성공적으로 저장되었어!');
    }
}

// 남아있는 커스텀 행이 없으면 저장 버튼 숨기기
function checkHeaderSaveButton() {
    const customRows = document.querySelectorAll('#shippingTable tbody tr.custom-row');
    if (customRows.length === 0) {
        document.getElementById('headerSaveBtn').style.display = 'none';
    }
}

// 기존 HTML에 포함되어 있던 기존 테이블 행들에도 기본 이벤트 연결
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#shippingTable tbody tr').forEach(row => {
        if (!row.classList.contains('farm-subtotal-row') && !row.classList.contains('date-total-row')) {
            for (let i = 0; i < 5; i++) {
                if (row.cells[i] && !row.cells[i].hasAttribute('rowspan')) {
                }
            }
        }
    });
});
</script>