<?php
/**
 * shippingmng.php : 출고 관리 화면 (DB 연동 및 동적 출력)
 */
declare(strict_types=1);

/* --------------------------------------------------
 * DB 연결 설정
 * -------------------------------------------------- */
require_once __DIR__ . '/db_connect.php';

// 데이터 조회 (farms 테이블과 JOIN하여 farm_name 가져오기)
try {
    $stmt = $pdo->query("
        SELECT o.*, f.farm_name 
        FROM outbound o 
        JOIN farms f ON o.farm_id = f.farm_id 
        ORDER BY o.outbound_date DESC, f.farm_name ASC, o.product_type ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$rows = [];
}

// 날짜별, 밭별 데이터 구조화 및 소계/총계 계산
$groupedData = [];
foreach ($rows as$row) {
    $date =$row['outbound_date'];
    $field =$row['farm_name']; // farms 테이블에서 조인해온 밭 이름 사용
    $groupedData[$date][$field][] =$row;
}
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
        display: none;
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
    .shipping-table tr.group-divider td {
        border-bottom: 1px solid #e0e0e0;
    }
    .shipping-table tr.section-divider td {
        border-bottom: 1px solid #e0e0e0;
    }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    
    .farm-subtotal-row td {
        background-color: #fffde7 !important;
        font-weight: bold;
    }
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
        height: 30px;
        padding: 4px 6px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
        font-family: inherit;
        box-sizing: border-box;
    }
    .shipping-form-group input[type="date"] {
        width: 130px; 
    }
    .shipping-form-group textarea {
        resize: none;
    }
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
                <?php 
                if (empty($groupedData)) {
                    echo '<tr><td colspan="10" style="padding:20px; text-align:center; color:#888;">등록된 출고 데이터가 없어.</td></tr>';
                } else {
                    foreach ($groupedData as$date => $fields) {$dateRowSpan = 0;
                        $dateTotalQty = 0;
                        $dateTotalDisposal = 0;
                        $dateTotalAuctionAmount = 0;
                        $dateTotalDepositAmount = 0;
                        
                        foreach ($fields as$field => $items) {$dateRowSpan += count($items) + 1;
                        }$dateRowSpan += 1;

                        $dateFormatted = date('m', strtotime($date)) . '<BR>/<BR>' . date('d', strtotime($date));$isFirstDateRow = true;

                        foreach ($fields as $field =>$items) {
                            $fieldRowSpan = count($items) + 1;
                            $fieldTotalQty = 0;
                            $fieldTotalDisposal = 0;
                            $fieldTotalAuctionAmount = 0;
                            $fieldTotalDepositAmount = 0;
                            $isFirstFieldRow = true;

                            foreach ($items as$item) {
                                $fieldTotalQty += (float)$item['quantity'];
                                $fieldTotalDisposal += (float)$item['disposal'];
                                $fieldTotalAuctionAmount += (float)$item['auction_total_amount'];
                                $fieldTotalDepositAmount += (float)$item['deposit_amount'];

                                $dateTotalQty += (float)$item['quantity'];
                                $dateTotalDisposal += (float)$item['disposal'];
                                $dateTotalAuctionAmount += (float)$item['auction_total_amount'];
                                $dateTotalDepositAmount += (float)$item['deposit_amount'];
                ?>
                <tr data-date="<?= htmlspecialchars($date) ?>" data-farm="<?= htmlspecialchars($field) ?>" data-product="<?= htmlspecialchars($item['product_type']) ?>" data-disposal="<?= htmlspecialchars($item['disposal']) ?>" data-is-new="false" data-auction-unit="<?= htmlspecialchars($item['auction_unit_price']) ?>" data-auction-total="<?= htmlspecialchars($item['auction_total_amount']) ?>" data-deposit-amount="<?= htmlspecialchars($item['deposit_amount']) ?>" data-qty="<?= htmlspecialchars($item['quantity']) ?>" data-auction-modified="false" data-qty-modified="false">
                    <?php if ($isFirstDateRow) { ?>
                        <td rowspan="<?= $dateRowSpan ?>" style="vertical-align: middle; background-color: #fafafa; border-bottom: 1px solid #e0e0e0;" data-col="date"><?= $dateFormatted ?></td>
                        <?php $isFirstDateRow = false; ?>
                    <?php } ?>

                    <?php if ($isFirstFieldRow) { ?>
                        <td rowspan="<?= $fieldRowSpan ?>" style="vertical-align: middle; background-color: #fafafa;" data-col="farm"><?= htmlspecialchars($field) ?></td>
                        <?php $isFirstFieldRow = false; ?>
                    <?php } ?>

                    <td data-col="product"><?= htmlspecialchars($item['product_type']) ?></td>
                    <td class="text-right" data-col="qty"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.')) ?></td>
                    <td class="text-right" data-col="disposal"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$item['disposal'], 2), '0'), '.')) ?></td>
                    <td class="text-right" data-col="auction-unit"><?= number_format((float)$item['auction_unit_price']) ?></td>
                    <td class="text-right" data-col="auction-total"><?= number_format((float)$item['auction_total_amount']) ?></td>
                    <td class="text-right" data-col="deposit-amount"><?= number_format((float)$item['deposit_amount']) ?></td>
                    <td class="text-right" data-col="deposit-unit"><?= number_format((float)$item['deposit_unit_price']) ?></td>
                    <td class="text-center" data-col="fee"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$item['commission_rate'], 2), '0'), '.')) ?>%</td>
                </tr>
                <?php
                            } // end foreach items
                            
                            $fieldAvgFee = count($items) > 0 ? array_sum(array_column($items, 'commission_rate')) / count($items) : 0;
                ?>
                <!-- 밭별 소계 -->
                <tr class="farm-subtotal-row group-divider" data-date="<?= htmlspecialchars($date) ?>" data-farm="<?= htmlspecialchars($field) ?>">
                    <td data-col="product">소계</td>
                    <td class="text-right" data-col="qty"><?= rtrim(rtrim(number_format($fieldTotalQty, 2), '0'), '.') ?></td>
                    <td class="text-right" data-col="disposal"><?= rtrim(rtrim(number_format($fieldTotalDisposal, 2), '0'), '.') ?></td>
                    <td class="text-right" data-col="auction-unit">-</td>
                    <td class="text-right" data-col="auction-total"><?= number_format($fieldTotalAuctionAmount) ?></td>
                    <td class="text-right" data-col="deposit-amount"><?= number_format($fieldTotalDepositAmount) ?></td>
                    <td class="text-right" data-col="deposit-unit">-</td>
                    <td class="text-center" data-col="fee"><?= round($fieldAvgFee) ?>%</td>
                </tr>
                <?php
                        } // end foreach fields

                        $dateAvgFee = count($rows) > 0 ? array_sum(array_column($rows, 'commission_rate')) / count($rows) : 0;
                ?>
                <!-- 날짜별 합계 -->
                <tr class="date-total-row section-divider" data-date="<?= htmlspecialchars($date) ?>">
                    <td>합계</td>
                    <td>-</td>
                    <td class="text-right"><?= rtrim(rtrim(number_format($dateTotalQty, 2), '0'), '.') ?></td>
                    <td class="text-right"><?= rtrim(rtrim(number_format($dateTotalDisposal, 2), '0'), '.') ?></td>
                    <td class="text-right">-</td>
                    <td class="text-right"><?= number_format($dateTotalAuctionAmount) ?></td>
                    <td class="text-right"><?= number_format($dateTotalDepositAmount) ?></td>
                    <td class="text-right">-</td>
                    <td class="text-center"><?= round($dateAvgFee) ?>%</td>
                </tr>
                <?php
                    } // end foreach groupedData
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

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

function formatNum(val) {
    if (val === '-' || val === '' || isNaN(val)) return '-';
    return Math.floor(Number(val)).toLocaleString();
}

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

function checkAndOpenEdit(row) {
    const isNewRow = row.dataset.isNew === 'true';
    const auctionUnit = parseFloat(row.dataset.auctionUnit) || 0;
    const auctionModified = row.dataset.auctionModified === 'true';

    const canOpen = isNewRow || (auctionUnit === 0 && !auctionModified);

    if (!canOpen) {
        alert('이 행은 조건에 부합하지 않아 기본정보를 수정할 수 없어!');
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
                <input type="text" id="edit-single-qty" class="dyn-product-qty" value="${row.dataset.qty || ''}" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>폐기</label>
                <input type="text" id="edit-single-disposal" class="dyn-product-disposal" value="${row.dataset.disposal || '0'}" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            </div>
        </div>
    `;

    document.getElementById('shippingModal').style.display = 'flex';
}

function closeShippingModal() {
    document.getElementById('shippingModal').style.display = 'none';
    currentEditingRow = null;
}

function saveShippingRow() {
    if (currentEditingRow) {
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
        currentEditingRow.dataset.qtyModified = 'true';

        const dateFormatted = dateVal.substring(5, 7) + '/' + dateVal.substring(8, 10);
        
        const dateCell = currentEditingRow.querySelector('[data-col="date"]');
        if (dateCell) dateCell.innerText = dateFormatted;
        const farmCell = currentEditingRow.querySelector('[data-col="farm"]');
        if (farmCell) farmCell.innerText = farmVal;
        const productCell = currentEditingRow.querySelector('[data-col="product"]');
        if (productCell) productCell.innerText = productVal;
        const qtyCell = currentEditingRow.querySelector('[data-col="qty"]');
        if (qtyCell) qtyCell.innerText = qtyVal;
        const disposalCell = currentEditingRow.querySelector('[data-col="disposal"]');
        if (disposalCell) disposalCell.innerText = disposalVal;

        document.getElementById('headerSaveBtn').style.display = 'inline-block';
        closeShippingModal();

    } else {
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
            newRow.dataset.disposal = disposalVal;
            newRow.dataset.isNew = 'true';
            newRow.dataset.qty = qtyVal;
            newRow.dataset.auctionUnit = '0';
            newRow.dataset.auctionTotal = '0';
            newRow.dataset.depositAmount = '0';
            newRow.dataset.auctionModified = 'false';
            newRow.dataset.qtyModified = 'false';

            newRow.innerHTML = `
                <td style="background-color: #fffde7;" data-col="date">${dateFormatted}</td>
                <td style="background-color: #fffde7;" data-col="farm">${farmVal}</td>
                <td style="background-color: #fffde7;" data-col="product">${productVal}</td>
                <td style="background-color: #fffde7;" class="text-right" data-col="qty">${qtyVal}</td>
                <td style="background-color: #fffde7;" class="text-right" data-col="disposal">${disposalVal}</td>
                <td class="text-right" data-col="auction-unit">-</td>
                <td class="text-right" data-col="auction-total">-</td>
                <td class="text-right" data-col="deposit-amount">-</td>
                <td class="text-right" data-col="deposit-unit">-</td>
                <td class="text-center"><button type="button" class="delete-btn">-</button></td>
            `;

            setupRowEvents(newRow);
            tableBody.insertBefore(newRow, tableBody.firstChild);
        });

        document.getElementById('headerSaveBtn').style.display = 'inline-block';
        closeShippingModal();
    }
}

function setupRowEvents(row) {
    const editCells = row.querySelectorAll('[data-col="date"], [data-col="farm"], [data-col="product"], [data-col="qty"], [data-col="disposal"]');
    editCells.forEach(cell => {
        cell.onclick = (e) => {
            e.stopPropagation();
            checkAndOpenEdit(row);
        };
    });

    const auctionCells = row.querySelectorAll('[data-col="auction-unit"], [data-col="auction-total"]');
    auctionCells.forEach(cell => {
        cell.onclick = (e) => {
            e.stopPropagation();
            checkAndOpenAuction(row);
        };
    });

    const depositCells = row.querySelectorAll('[data-col="deposit-amount"], [data-col="deposit-unit"]');
    depositCells.forEach(cell => {
        cell.onclick = (e) => {
            e.stopPropagation();
            checkAndOpenDeposit(row);
        };
    });

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

function checkAndOpenAuction(row) {
    const isNewRow = row.dataset.isNew === 'true';
    const qty = parseFloat(row.dataset.qty) || 0;
    const qtyModified = row.dataset.qtyModified === 'true';

    if (isNewRow) {
        alert('New Row일 때는 경매단가 항목 수정팝업을 오픈할 수 없어!');
        return;
    }

    const canOpen = qty >= 1 && !qtyModified;

    if (!canOpen) {
        alert('수량이 변경되었거나 조건에 맞지 않아 경매단가 팝업을 열 수 없어!');
        return;
    }

    currentEditingRow = row;
    const currentUnit = row.dataset.auctionUnit ? Number(row.dataset.auctionUnit) / 100 : '';
    document.getElementById('auction-unit').value = currentUnit !== 0 ? currentUnit : '';
    document.getElementById('auction-total').value = row.dataset.auctionTotal || '';
    document.getElementById('auctionModal').style.display = 'flex';
}

function saveAuctionRow() {
    if (!currentEditingRow) return;

    const rawUnit = parseFloat(document.getElementById('auction-unit').value) || 0;
    const unitVal = rawUnit * 100;
    const qty = parseFloat(currentEditingRow.dataset.qty) || 1;
    const totalVal = unitVal * qty;

    currentEditingRow.dataset.auctionUnit = unitVal;
    currentEditingRow.dataset.auctionTotal = totalVal;
    currentEditingRow.dataset.auctionModified = 'true';

    const unitCell = currentEditingRow.querySelector('[data-col="auction-unit"]');
    if (unitCell) unitCell.innerText = unitVal !== 0 ? formatNum(unitVal) : '-';
    
    const totalCell = currentEditingRow.querySelector('[data-col="auction-total"]');
    if (totalCell) totalCell.innerText = totalVal !== 0 ? formatNum(totalVal) : '-';

    document.getElementById('headerSaveBtn').style.display = 'inline-block';
    document.getElementById('auctionModal').style.display = 'none';
    currentEditingRow = null;
}

function checkAndOpenDeposit(row) {
    const isNewRow = row.dataset.isNew === 'true';
    const auctionUnit = parseFloat(row.dataset.auctionUnit) || 0;
    const auctionModified = row.dataset.auctionModified === 'true';

    if (isNewRow) {
        alert('New Row일 때는 입금액 항목 수정팝업을 오픈할 수 없어!');
        return;
    }

    const canOpen = auctionUnit >= 1 && !auctionModified;

    if (!canOpen) {
        alert('경매단가가 변경되었거나 조건에 맞지 않아 입금액 팝업을 열 수 없어!');
        return;
    }

    currentEditingRow = row;
    const depositAmount = parseFloat(row.dataset.depositAmount) || 0;
    document.getElementById('deposit-amount').value = depositAmount !== 0 ? depositAmount / 100 : '';
    document.getElementById('depositModal').style.display = 'flex';
}

function saveDepositRow() {
    if (!currentEditingRow) return;

    const rawDeposit = parseFloat(document.getElementById('deposit-amount').value) || 0;
    const depositVal = rawDeposit * 100;
    const qty = parseFloat(currentEditingRow.dataset.qty) || 1;
    const depositUnit = qty > 0 ? (depositVal / qty) : 0;

    currentEditingRow.dataset.depositAmount = depositVal;
    currentEditingRow.dataset.depositUnit = depositUnit;

    const depositCell = currentEditingRow.querySelector('[data-col="deposit-amount"]');
    if (depositCell) depositCell.innerText = depositVal !== 0 ? formatNum(depositVal) : '-';

    const depositUnitCell = currentEditingRow.querySelector('[data-col="deposit-unit"]');
    if (depositUnitCell) depositUnitCell.innerText = depositUnit !== 0 ? formatNum(depositUnit) : '-';

    document.getElementById('headerSaveBtn').style.display = 'inline-block';
    document.getElementById('depositModal').style.display = 'none';
    currentEditingRow = null;
}

function saveNewRowAction() {
    if (confirm('작성 및 수정된 모든 내용을 저장할까?')) {
        document.querySelectorAll('#shippingTable tbody tr.custom-row').forEach(row => {
            row.dataset.isNew = 'false';
            row.querySelectorAll('td').forEach(td => {
                td.style.backgroundColor = '#fff';
            });
        });
        document.getElementById('headerSaveBtn').style.display = 'none';
        alert('성공적으로 저장되었어!');
    }
}

function checkHeaderSaveButton() {
    const customRows = document.querySelectorAll('#shippingTable tbody tr.custom-row');
    if (customRows.length === 0) {
        document.getElementById('headerSaveBtn').style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#shippingTable tbody tr').forEach(row => {
        if (!row.classList.contains('farm-subtotal-row') && !row.classList.contains('date-total-row')) {
            setupRowEvents(row);
        }
    });
});
</script>