<?php

return [
    'methods' => ['CASH' => 'Tiền mặt', 'BANK_TRANSFER' => 'Chuyển khoản', 'MOMO' => 'MoMo', 'VNPAY' => 'VNPay', 'ZALOPAY' => 'ZaloPay', 'CREDIT_CARD' => 'Thẻ'],
    'statuses' => ['PENDING' => 'Chờ xử lý', 'PAID' => 'Đã thu', 'PARTIALLY_PAID' => 'Thu một phần (cần đối soát)', 'FAILED' => 'Thất bại',
        'REFUNDED' => 'Đã hoàn tiền', 'PARTIALLY_REFUNDED' => 'Đã hoàn một phần', 'APPROVED' => 'Đã duyệt, chờ trả tiền', 'REJECTED' => 'Đã từ chối', 'PROCESSING' => 'Đang xử lý'],
];
