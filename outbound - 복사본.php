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
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        width: 100%;
    }
    .shipping-title {
        font-size: 16px;
        font-weight: bold;
        color: #2c3e50;
        margin: 0;
        flex: 1;
        text-align: center;
        padding-left: 65px; /* 우측 버튼들 크기만큼 여백을 주어 타이틀을 정중앙에 배치 */
    }
    .header-btn-group {
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
    /* 날짜 아래(날짜별 합계 아랫줄) 굵은 실선 적용 */
    .shipping-table tr.section-divider td {
        border-bottom: 2.5px solid #2c3e50;
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
        width: 90%;
        max-width: 340px;
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
    .shipping-form-group {
        margin-bottom: 12px;
    }
    .shipping-form-group label {
        display: block;
        font-size: 12px;
        font-weight: bold;
        color: #555;
        margin-bottom: 4px;
    }
    .shipping-form-group input[type="date"],
    .shipping-form-group input[type="text"],
    .shipping-form-group select,
    .shipping-form-group textarea {
        width: 100%;
        padding: 6px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
        font-family: inherit;
        box-sizing: border-box;
    }
    .shipping-form-group textarea {
        resize: none;
    }
    .shipping-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 15px;
    }
    .shipping-modal-actions button {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
    }
    .btn-save { background-color: #4a148c; color: #fff; width: 100%; }
</style>

<div class="shipping-wrap">
    <div class="shipping-header-area">
        <h2 class="shipping-title">📦 출고 관리</h2>
        <div class="header-btn-group">
            <button type="button" class="header-save-btn" id="headerSaveBtn" onclick="saveNewRowAction()">저장</button>
            <button type="button" class="add-row-btn" onclick="addNewShippingRow()" title="한 줄 추가">+</button>
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
                <tr>
                    <td rowspan="16" style="vertical-align: middle; background-color: #fafafa; border-bottom: 2.5px solid #2c3e50;">10<BR>/<BR>10</td>
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
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr>
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
                <tr class="farm-subtotal-row group-divider">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <tr>
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
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr>
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
                <tr class="farm-subtotal-row group-divider">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <tr>
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
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr>
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
                <tr class="farm-subtotal-row group-divider">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <!-- 10/10 날짜 총합 (옅은 보라색) - 날짜 아래 굵은 실선 적용 -->
                <tr class="date-total-row section-divider">
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
				<tr>
                    <td rowspan="11" style="vertical-align: middle; background-color: #fafafa; border-bottom: 2.5px solid #2c3e50;">10<BR>/<BR>09</td>
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
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr>
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
                <tr class="farm-subtotal-row group-divider">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <tr>
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
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr>
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
                <tr class="farm-subtotal-row group-divider">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <!-- 10/09 날짜 총합 (옅은 보라색) - 날짜 아래 굵은 실선 적용 -->
                <tr class="date-total-row section-divider">
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
				<tr>
                    <td rowspan="11" style="vertical-align: middle; background-color: #fafafa; border-bottom: 2.5px solid #2c3e50;">10<BR>/<BR>08</td>
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
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr>
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
                <tr class="farm-subtotal-row group-divider">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <tr>
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
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">0</td>
                    <td class="text-right">20,000</td>
                    <td class="text-right">60,000</td>
                    <td class="text-right">59,000</td>
                    <td class="text-right">19,666</td>
                    <td class="text-center">2%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">0</td>
                    <td class="text-right">30,000</td>
                    <td class="text-right">120,000</td>
                    <td class="text-right">110,000</td>
                    <td class="text-right">27,500</td>
                    <td class="text-center">8%</td>
                </tr>
                <tr>
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
                <tr class="farm-subtotal-row group-divider">
                    <td>소계</td>
                    <td class="text-right">14</td>
                    <td class="text-right">0</td>
                    <td class="text-right">-</td>
                    <td class="text-right">400,000</td>
                    <td class="text-right">378,000</td>
                    <td class="text-right">-</td>
                    <td class="text-center">6%</td>
                </tr>
                <!-- 10/08 날짜 총합 (옅은 보라색) - 날짜 아래 굵은 실선 적용 -->
                <tr class="date-total-row section-divider">
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

<!-- 출고 정보 입력 팝업 모달 (기본 정보) -->
<div class="shipping-modal-overlay" id="shippingModal">
    <div class="shipping-modal-content">
        <h3>출고 정보 입력</h3>
        <div class="shipping-form-group">
            <label for="shipping-date">날짜</label>
            <input type="date" id="shipping-date">
        </div>
        <div class="shipping-form-group">
            <label for="shipping-farm">밭 이름</label>
            <select id="shipping-farm">
                <option value="대">대</option>
                <option value="한">한</option>
                <option value="민">민</option>
                <option value="국">국</option>
            </select>
        </div>
        <div class="shipping-form-group">
            <label for="shipping-product">상품 종류</label>
            <select id="shipping-product">
                <option value="찹찹">찹찹</option>
                <option value="바찹">바찹</option>
                <option value="바라">바라</option>
                <option value="옆순">옆순</option>
            </select>
        </div>
        <div class="shipping-form-group">
            <label for="shipping-qty">수량</label>
            <input type="text" id="shipping-qty" placeholder="숫자만 입력" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        </div>
        <div class="shipping-form-group">
            <label for="shipping-disposal">폐기</label>
            <input type="text" id="shipping-disposal" placeholder="숫자만 입력" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        </div>
        <div class="shipping-form-group">
            <label for="shipping-memo">비고</label>
            <textarea id="shipping-memo" rows="3" placeholder="내용을 입력하세요"></textarea>
        </div>
        <div class="shipping-modal-actions">
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

// 그리드에 새로운 빈 줄 추가 (맨 위에 추가)
function addNewShippingRow() {
    const tableBody = document.querySelector('#shippingTable tbody');
    const newRow = document.createElement('tr');
    newRow.className = 'custom-row';
    
    // 기본값 설정
    const todayStr = new Date().toISOString().split('T')[0];
    newRow.dataset.date = todayStr;
    newRow.dataset.farm = '대';
    newRow.dataset.product = '찹찹';
    newRow.dataset.qty = '0';
    newRow.dataset.disposal = '0';
    newRow.dataset.memo = '';
    newRow.dataset.auctionUnit = '';
    newRow.dataset.auctionTotal = '';
    newRow.dataset.depositAmount = '';
    newRow.dataset.depositUnit = '';
    newRow.dataset.commission = '-';

    // 날짜 포맷 변환 표시용
    const dateFormatted = todayStr.substring(5, 7) + '/' + todayStr.substring(8, 10);

    newRow.innerHTML = `
        <td style="border-bottom: 2.5px solid #2c3e50;">${dateFormatted}</td>
        <td>대</td>
        <td>찹찹</td>
        <td class="text-right">0</td>
        <td class="text-right">0</td>
        <td class="text-right">-</td>
        <td class="text-right">-</td>
        <td class="text-right">-</td>
        <td class="text-right">-</td>
        <td class="text-center"><button type="button" class="delete-btn">-</button></td>
    `;

    const cells = newRow.querySelectorAll('td');

    // 1. 날짜, 밭 이름, 상품 종류, 수량, 폐기 터치 시 메인 팝업 오픈
    cells[0].onclick = () => openShippingModal(newRow);
    cells[1].onclick = () => openShippingModal(newRow);
    cells[2].onclick = () => openShippingModal(newRow);
    cells[3].onclick = () => openShippingModal(newRow);
    cells[4].onclick = () => openShippingModal(newRow);

    // 2. 경매 단가, 경매 총액 터치 시 경매 팝업 오픈
    cells[5].onclick = () => openAuctionModal(newRow);
    cells[6].onclick = () => openAuctionModal(newRow);

    // 3. 입금액, 입금 단가 터치 시 입금액 팝업 오픈
    cells[7].onclick = () => openDepositModal(newRow);
    cells[8].onclick = () => openDepositModal(newRow);

    // 4. 수수료(-) 버튼 클릭 시 행 삭제
    const deleteBtn = cells[9].querySelector('.delete-btn');
    deleteBtn.onclick = (e) => {
        e.stopPropagation();
        if (confirm('이 줄을 삭제할까?')) {
            newRow.remove();
            checkHeaderSaveButton();
        }
    };

    // 테이블의 맨 위에 삽입
    tableBody.insertBefore(newRow, tableBody.firstChild);
    
    // 새 줄이 추가되었으므로 헤더에 "저장" 버튼 표시
    document.getElementById('headerSaveBtn').style.display = 'inline-block';
    
    // 추가 직후 바로 기본 정보 수정 모달 띄우기
    openShippingModal(newRow);
}

// 헤더의 "저장" 버튼 클릭 시
function saveNewRowAction() {
    if (confirm('작성 중인 내용을 저장할까?')) {
        document.getElementById('headerSaveBtn').style.display = 'none';
    }
}

// 남아있는 커스텀 행이 없으면 저장 버튼 숨기기
function checkHeaderSaveButton() {
    const customRows = document.querySelectorAll('#shippingTable tbody tr.custom-row');
    if (customRows.length === 0) {
        document.getElementById('headerSaveBtn').style.display = 'none';
    }
}

// 메인 팝업 열기
function openShippingModal(row) {
    currentEditingRow = row;
    
    document.getElementById('shipping-date').value = row.dataset.date || '';
    document.getElementById('shipping-farm').value = row.dataset.farm || '대';
    document.getElementById('shipping-product').value = row.dataset.product || '찹찹';
    document.getElementById('shipping-qty').value = row.dataset.qty !== '0' ? row.dataset.qty : '';
    document.getElementById('shipping-disposal').value = row.dataset.disposal !== '0' ? row.dataset.disposal : '';
    document.getElementById('shipping-memo').value = row.dataset.memo || '';

    document.getElementById('shippingModal').style.display = 'flex';
}

// 메인 팝업 저장
function saveShippingRow() {
    if (!currentEditingRow) return;

    const dateVal = document.getElementById('shipping-date').value;
    const farmVal = document.getElementById('shipping-farm').value;
    const productVal = document.getElementById('shipping-product').value;
    const qtyVal = document.getElementById('shipping-qty').value || '0';
    const disposalVal = document.getElementById('shipping-disposal').value || '0';
    const memoVal = document.getElementById('shipping-memo').value;

    if (!dateVal) {
        alert('날짜를 입력해 줘!');
        return;
    }

    currentEditingRow.dataset.date = dateVal;
    currentEditingRow.dataset.farm = farmVal;
    currentEditingRow.dataset.product = productVal;
    currentEditingRow.dataset.qty = qtyVal;
    currentEditingRow.dataset.disposal = disposalVal;
    currentEditingRow.dataset.memo = memoVal;

    const dateFormatted = dateVal.substring(5, 7) + '/' + dateVal.substring(8, 10);
    const cells = currentEditingRow.querySelectorAll('td');
    cells[0].innerText = dateFormatted;
    cells[1].innerText = farmVal;
    cells[2].innerText = productVal;
    cells[3].innerText = qtyVal;
    cells[4].innerText = disposalVal;

    // 수량이 변경되었을 때 연관 값 재계산
    recalculateRow(currentEditingRow);

    document.getElementById('shippingModal').style.display = 'none';
    currentEditingRow = null;
}

// 경매 팝업 열기
function openAuctionModal(row) {
    currentEditingRow = row;
    const currentUnit = row.dataset.auctionUnit ? Number(row.dataset.auctionUnit) / 100 : '';
    document.getElementById('auction-unit').value = currentUnit !== 0 ? currentUnit : '';
    document.getElementById('auction-total').value = row.dataset.auctionTotal || '';
    document.getElementById('auctionModal').style.display = 'flex';
}

// 경매 팝업 저장 (경매단가 * 100 반영 및 경매총액, 수수료 재계산)
function saveAuctionRow() {
    if (!currentEditingRow) return;

    const rawUnit = parseFloat(document.getElementById('auction-unit').value) || 0;
    const unitVal = rawUnit * 100; // 경매단가 * 100
    const qty = parseFloat(currentEditingRow.dataset.qty) || 1;
    const totalVal = unitVal * qty; // 경매총액 = 수량 * (경매단가 * 100)

    currentEditingRow.dataset.auctionUnit = unitVal;
    currentEditingRow.dataset.auctionTotal = totalVal;

    const cells = currentEditingRow.querySelectorAll('td');
    cells[5].innerText = unitVal !== 0 ? formatNum(unitVal) : '-';
    cells[6].innerText = totalVal !== 0 ? formatNum(totalVal) : '-';

    // 수수료 재계산 반영
    recalculateRow(currentEditingRow);

    document.getElementById('auctionModal').style.display = 'none';
    currentEditingRow = null;
}

// 입금액 팝업 열기
function openDepositModal(row) {
    currentEditingRow = row;
    const currentDeposit = row.dataset.depositAmount ? Number(row.dataset.depositAmount) / 100 : '';
    document.getElementById('deposit-amount').value = currentDeposit !== 0 ? currentDeposit : '';
    document.getElementById('depositModal').style.display = 'flex';
}

// 입금액 팝업 저장 (입금액 * 100 반영 및 입금단가, 수수료 재계산)
function saveDepositRow() {
    if (!currentEditingRow) return;

    const rawDeposit = parseFloat(document.getElementById('deposit-amount').value) || 0;
    const depositVal = rawDeposit * 100; // 입금액 * 100
    const qty = parseFloat(currentEditingRow.dataset.qty) || 1;
    const depositUnit = qty > 0 ? (depositVal / qty) : 0; // 입금단가 = 입금액 / 수량

    currentEditingRow.dataset.depositAmount = depositVal;
    currentEditingRow.dataset.depositUnit = depositUnit;

    const cells = currentEditingRow.querySelectorAll('td');
    cells[7].innerText = depositVal !== 0 ? formatNum(depositVal) : '-';
    cells[8].innerText = depositUnit !== 0 ? formatNum(depositUnit) : '-';

    // 수수료 재계산 반영
    recalculateRow(currentEditingRow);

    document.getElementById('depositModal').style.display = 'none';
    currentEditingRow = null;
}

// 수량/금액 변경 시 연관 값 및 수수료 재계산 보조 함수
function recalculateRow(row) {
    const qty = parseFloat(row.dataset.qty) || 1;
    const unitVal = parseFloat(row.dataset.auctionUnit) || 0;
    if (unitVal > 0) {
        const totalVal = unitVal * qty;
        row.dataset.auctionTotal = totalVal;
        row.querySelectorAll('td')[6].innerText = formatNum(totalVal);
    }
    const depositVal = parseFloat(row.dataset.depositAmount) || 0;
    if (depositVal > 0) {
        const depositUnit = qty > 0 ? (depositVal / qty) : 0;
        row.dataset.depositUnit = depositUnit;
        row.querySelectorAll('td')[8].innerText = formatNum(depositUnit);
    }
    
    // 경매총액과 입금액을 바탕으로 수수료 재계산 로직
    const auctionTotal = parseFloat(row.dataset.auctionTotal) || 0;
    const depAmount = parseFloat(row.dataset.depositAmount) || 0;
    if (auctionTotal > 0) {
        const commissionVal = Math.round(((auctionTotal - depAmount) / auctionTotal) * 100) + '%';
        row.dataset.commission = commissionVal;
    }
}
</script>