<?php
/**
 * shippingmng.php : 출고 관리 화면 (DB 연동 없이 퍼블리싱 화면 구성)
 */
declare(strict_types=1);
?>
<style>
    .shipping-wrap {
        padding: 10px;
        background: #fff;
        border-radius: 8px;
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
        padding: 8px 4px;
        border: 1px solid #d1c4e9;
    }
    .shipping-table td {
        padding: 6px 4px;
        border: 1px solid #e0e0e0;
        background-color: #fff;
    }
    .shipping-table tr.group-divider td {
        border-bottom: 2px dashed #9e9e9e;
    }
    .shipping-table tr.section-divider td {
        border-bottom: 2px solid #2c3e50;
    }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
</style>

<div class="shipping-wrap">
    <h2 style="font-size: 16px; font-weight: bold; margin-bottom: 12px; color: #2c3e50; text-align: center;">📦 출고 관리</h2>
    
    <div class="shipping-table-container">
        <table class="shipping-table">
            <thead>
                <tr>
                    <th>날짜</th>
                    <th>밭 이름</th>
                    <th>상품 종류</th>
                    <th>수량</th>
                    <th>경매 단가</th>
                    <th>입금액</th>
                    <th>개별 수익</th>
                    <th>수수료(%)</th>
                </tr>
            </thead>
            <tbody>
                <!-- 첫 번째 그룹: 10월 09일 / 대 -->
                <tr>
                    <td rowspan="4" style="vertical-align: middle; background-color: #fafafa;">10월 09일</td>
                    <td rowspan="4" style="vertical-align: middle; background-color: #fafafa;">대</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">100</td>
                    <td class="text-right">90</td>
                    <td class="text-right">50</td>
                    <td class="text-center">10%</td>
                </tr>
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">200</td>
                    <td class="text-right">190</td>
                    <td class="text-right">66.66667</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">300</td>
                    <td class="text-right">290</td>
                    <td class="text-right">75</td>
                    <td class="text-center">3%</td>
                </tr>
                <tr class="group-divider">
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">400</td>
                    <td class="text-right">390</td>
                    <td class="text-right">80</td>
                    <td class="text-center">3%</td>
                </tr>

                <!-- 첫 번째 그룹: 10월 09일 / 한 -->
                <tr>
                    <td rowspan="4" style="display:none;">10월 09일</td>
                    <td rowspan="4" style="vertical-align: middle; background-color: #fafafa;">한</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">100</td>
                    <td class="text-right">90</td>
                    <td class="text-right">50</td>
                    <td class="text-center">10%</td>
                </tr>
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">200</td>
                    <td class="text-right">190</td>
                    <td class="text-right">66.66667</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">300</td>
                    <td class="text-right">290</td>
                    <td class="text-right">75</td>
                    <td class="text-center">3%</td>
                </tr>
                <tr class="section-divider">
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">400</td>
                    <td class="text-right">390</td>
                    <td class="text-right">80</td>
                    <td class="text-center">3%</td>
                </tr>

                <!-- 두 번째 블록: 10월 09일 / 대 -->
                <tr>
                    <td rowspan="4" style="vertical-align: middle; background-color: #fafafa;">10월 09일</td>
                    <td rowspan="4" style="vertical-align: middle; background-color: #fafafa;">대</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">100</td>
                    <td class="text-right">90</td>
                    <td class="text-right">50</td>
                    <td class="text-center">10%</td>
                </tr>
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">200</td>
                    <td class="text-right">190</td>
                    <td class="text-right">66.66667</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">300</td>
                    <td class="text-right">290</td>
                    <td class="text-right">75</td>
                    <td class="text-center">3%</td>
                </tr>
                <tr class="group-divider">
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">400</td>
                    <td class="text-right">390</td>
                    <td class="text-right">80</td>
                    <td class="text-center">3%</td>
                </tr>

                <!-- 두 번째 블록: 10월 09일 / 한 -->
                <tr>
                    <td rowspan="4" style="display:none;">10월 09일</td>
                    <td rowspan="4" style="vertical-align: middle; background-color: #fafafa;">한</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">100</td>
                    <td class="text-right">90</td>
                    <td class="text-right">50</td>
                    <td class="text-center">10%</td>
                </tr>
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">200</td>
                    <td class="text-right">190</td>
                    <td class="text-right">66.66667</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">300</td>
                    <td class="text-right">290</td>
                    <td class="text-right">75</td>
                    <td class="text-center">3%</td>
                </tr>
                <tr class="section-divider">
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">400</td>
                    <td class="text-right">390</td>
                    <td class="text-right">80</td>
                    <td class="text-center">3%</td>
                </tr>

                <!-- 두 번째 블록: 10월 09일 / 민 -->
                <tr>
                    <td rowspan="4" style="vertical-align: middle; background-color: #fafafa;">10월 09일</td>
                    <td rowspan="4" style="vertical-align: middle; background-color: #fafafa;">민</td>
                    <td>찹찹</td>
                    <td class="text-right">2</td>
                    <td class="text-right">100</td>
                    <td class="text-right">90</td>
                    <td class="text-right">50</td>
                    <td class="text-center">10%</td>
                </tr>
                <tr>
                    <td>바찹</td>
                    <td class="text-right">3</td>
                    <td class="text-right">200</td>
                    <td class="text-right">190</td>
                    <td class="text-right">66.66667</td>
                    <td class="text-center">5%</td>
                </tr>
                <tr>
                    <td>바라</td>
                    <td class="text-right">4</td>
                    <td class="text-right">300</td>
                    <td class="text-right">290</td>
                    <td class="text-right">75</td>
                    <td class="text-center">3%</td>
                </tr>
                <tr>
                    <td>옆순</td>
                    <td class="text-right">5</td>
                    <td class="text-right">400</td>
                    <td class="text-right">390</td>
                    <td class="text-right">80</td>
                    <td class="text-center">3%</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>