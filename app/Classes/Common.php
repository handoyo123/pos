<?php

namespace App\Classes;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Payment;
use App\Models\ProductCustomField;
use App\Models\ProductDetails;
use App\Models\StockAdjustment;
use App\Models\StockHistory;
use App\Models\Unit;
use App\Models\WarehouseHistory;
use Carbon\Carbon;
use Examyou\RestAPI\Exceptions\RelatedResourceNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use Vinkla\Hashids\Facades\Hashids;

class Common
{
    public static function allUnits(): array
    {
        return [
            'piece' => [
                'name' => 'piece',
                'short_name' => 'pc',
                'operator' => 'multiply',
                'operator_value' => '1',
            ],
            'meter' => [
                'name' => 'meter',
                'short_name' => 'm',
                'operator' => 'multiply',
                'operator_value' => '1',
            ],
            'kilogram' => [
                'name' => 'kilogram',
                'short_name' => 'kg',
                'operator' => 'multiply',
                'operator_value' => '1',
            ],
            'liter' => [
                'name' => 'liter',
                'short_name' => 'l',
                'operator' => 'multiply',
                'operator_value' => '1',
            ]
        ];
    }

    /**
     * @param $allBaseUnits
     * @param string $baseUnitId
     * @return array|mixed
     */
    public static function allBaseUnitArray($allBaseUnits, string $baseUnitId = 'all')
    {
        $allUnitArray = [];

        foreach ($allBaseUnits as $allBaseUnit) {
            $allUnitArray[$allBaseUnit->id][] = [
                'id' => $allBaseUnit->id,
                'name' => $allBaseUnit->name,
                'operator' => $allBaseUnit->operator,
                'operator_value' => $allBaseUnit->operator_value,
                'short_name' => $allBaseUnit->short_name
            ];

            $allUnitCollections = Unit::select('id', 'name', 'short_name', 'operator', 'operator_value')->where('parent_id', $allBaseUnit->id)->get();
            foreach ($allUnitCollections as $allUnitCollection) {
                $allUnitArray[$allBaseUnit->id][] = [
                    'id' => $allUnitCollection->id,
                    'name' => $allUnitCollection->name,
                    'operator' => $allUnitCollection->operator,
                    'operator_value' => $allUnitCollection->operator_value,
                    'short_name' => $allUnitCollection->short_name
                ];
            }
        }

        return $baseUnitId != 'all' ? $allUnitArray[$baseUnitId] : $allUnitArray;
    }

    /** @noinspection PhpUndefinedFieldInspection
     * @throws RelatedResourceNotFoundException
     */
    public static function updateWarehouseHistory($type, $typeObject, $action = "delete")
    {
        if ($type == 'order') {
            $orderType = $typeObject->order_type;

            // Deleting Order and order item
            // Before inserting new
            WarehouseHistory::where(function ($query) use ($orderType) {
                $query->where('type', 'order-items')
                    ->orWhere('type', $orderType);
            })
                ->where('order_id', $typeObject->id)
                ->delete();

            if ($action == "add_edit") {
                $warehouseHistory = new WarehouseHistory();
                $warehouseHistory->date = $typeObject->order_date;
                $warehouseHistory->order_id = $typeObject->id;
                $warehouseHistory->warehouse_id = $typeObject->warehouse_id;
                $warehouseHistory->user_id = $typeObject->user_id;
                $warehouseHistory->amount = $typeObject->total;
                $warehouseHistory->status = $typeObject->payment_status;
                $warehouseHistory->type = $typeObject->order_type;
                $warehouseHistory->quantity = $typeObject->total_quantity;
                $warehouseHistory->updated_at = Carbon::now();
                $warehouseHistory->transaction_number = $typeObject->invoice_number;
                $warehouseHistory->save();

                // Saving order items
                $orderItems = $typeObject->items;

                foreach ($orderItems as $orderItem) {
                    $warehouseHistory = new WarehouseHistory();
                    $warehouseHistory->date = $typeObject->order_date;
                    $warehouseHistory->order_id = $typeObject->id;
                    $warehouseHistory->order_item_id = $orderItem->id;
                    $warehouseHistory->warehouse_id = $typeObject->warehouse_id;
                    $warehouseHistory->user_id = $typeObject->user_id;
                    $warehouseHistory->product_id = $orderItem->product_id;
                    $warehouseHistory->amount = $orderItem->subtotal;
                    $warehouseHistory->status = $typeObject->payment_status;
                    $warehouseHistory->type = "order-items";
                    $warehouseHistory->quantity = $orderItem->quantity;
                    $warehouseHistory->updated_at = Carbon::now();
                    $warehouseHistory->transaction_number = $typeObject->invoice_number;
                    $warehouseHistory->save();
                }
            }

            Common::updateOrderAmount($typeObject->id);
        } else {
            if ($type == 'payment') {
                $paymentType = 'payment-' . $typeObject->payment_type;

                // Deleting Order and order item
                // Before inserting new
                WarehouseHistory::where('type', $paymentType)
                    ->where('payment_id', $typeObject->id)
                    ->delete();

                if ($action == "add_edit") {
                    $warehouseHistory = new WarehouseHistory();
                    $warehouseHistory->date = $typeObject->date;
                    $warehouseHistory->payment_id = $typeObject->id;
                    $warehouseHistory->warehouse_id = $typeObject->warehouse_id;
                    $warehouseHistory->user_id = $typeObject->user_id;
                    $warehouseHistory->amount = $typeObject->amount;
                    $warehouseHistory->status = "paid";
                    $warehouseHistory->type = $paymentType;
                    $warehouseHistory->quantity = 0;
                    $warehouseHistory->updated_at = Carbon::now();
                    $warehouseHistory->transaction_number = $typeObject->payment_number;
                    $warehouseHistory->save();

                    $paymentOrders = OrderPayment::where('payment_id', $typeObject->id)->get();
                    if($paymentOrders){
                        foreach ($paymentOrders as $paymentOrder) {
                            $warehouseHistory = new WarehouseHistory();
                            $warehouseHistory->date = $typeObject->date;
                            $warehouseHistory->payment_id = $typeObject->id;
                            $warehouseHistory->warehouse_id = $typeObject->warehouse_id;
                            $warehouseHistory->user_id = $typeObject->user_id;
                            $warehouseHistory->order_id = $paymentOrder->order_id;
                            $warehouseHistory->amount = $paymentOrder->amount;
                            $warehouseHistory->status = "paid";
                            $warehouseHistory->type = "payment-orders";
                            $warehouseHistory->quantity = 0;
                            $warehouseHistory->updated_at = Carbon::now();
                            $warehouseHistory->transaction_number = $typeObject->payment_number;
                            $warehouseHistory->save();
                        }
                    }
                }


                Common::updateUserAmount($typeObject->user_id, $typeObject->warehouse_id);
            }
        }
    }

    public static function updateOrderAmount($orderId)
    {
        $order = Order::find($orderId);

        // In delete order case order will not be available
        // So no need to update order details like due, paid amount
        // But we will updateUserAmount from the OrderController
        if ($order) {
            $totalPaidAmount = OrderPayment::where('order_id', $order->id)->sum('amount');
            $dueAmount = round($order->total - $totalPaidAmount, 2);

            if ($dueAmount <= 0) {
                $orderPaymentStatus = 'paid';
            } else if ($dueAmount >= $order->total) {
                $orderPaymentStatus = 'unpaid';
            } else {
                $orderPaymentStatus = 'partially_paid';
            }

            $order->due_amount = $dueAmount;
            $order->paid_amount = $totalPaidAmount;
            $order->payment_status = $orderPaymentStatus;
            $order->save();

            // Update Customer or Supplier total amount, due amount, paid amount
            self::updateUserAmount($order->user_id, $order->warehouse_id);
        }
    }

    /**
     * @param $userId
     * @param $warehouseId
     * @return void
     */
    public static function updateUserAmount($userId, $warehouseId)
    {
        $user = Customer::withoutGlobalScope('type')->find($userId);
        $userDetails = $user->details;

        $totalPurchaseAmount = Order::where('user_id', '=', $user->id)
            ->where('warehouse_id', '=', $warehouseId)
            ->where('order_type', '=', 'purchases')
            ->sum('total');
        $totalPurchaseReturnAmount = Order::where('user_id', '=', $user->id)
            ->where('warehouse_id', '=', $warehouseId)
            ->where('order_type', '=', 'purchase-returns')
            ->sum('total');

        $totalSalesAmount = Order::where('user_id', '=', $user->id)
            ->where('warehouse_id', '=', $warehouseId)
            ->where('order_type', '=', 'sales')
            ->sum('total');
        $totalSalesReturnAmount = Order::where('user_id', '=', $user->id)
            ->where('warehouse_id', '=', $warehouseId)
            ->where('order_type', '=', 'sales-returns')
            ->sum('total');

        // Amount generated by payments
        $totalPaidAmountByUser = Payment::where('user_id', $user->id)->where('warehouse_id', '=', $warehouseId)->where('payment_type', "in")->sum('amount');
        $totalPaidAmountToUser = Payment::where('user_id', $user->id)->where('warehouse_id', '=', $warehouseId)->where('payment_type', "out")->sum('amount');
        $userTotalPaidPayment = $totalPaidAmountByUser - $totalPaidAmountToUser;

        // Amount generated by orders
        $userWillPay = $totalSalesAmount + $totalPurchaseReturnAmount;
        $userWillReceive = $totalPurchaseAmount + $totalSalesReturnAmount;
        $userTotalOrderAmount = $userWillPay - $userWillReceive;

        $purchaseOrderCount = Order::where('user_id', '=', $user->id)
            ->where('order_type', '=', 'purchases')
            ->where('warehouse_id', '=', $warehouseId)
            ->count();

        $purchaseReturnOrderCount = Order::where('user_id', '=', $user->id)
            ->where('order_type', '=', 'purchase-returns')
            ->where('warehouse_id', '=', $warehouseId)
            ->count();

        $salesOrderCount = Order::where('user_id', '=', $user->id)
            ->where('order_type', '=', 'sales')
            ->where('warehouse_id', '=', $warehouseId)
            ->count();

        $salesReturnOrderCount = Order::where('user_id', '=', $user->id)
            ->where('order_type', '=', 'sales-returns')
            ->where('warehouse_id', '=', $warehouseId)
            ->count();

        $userDetails->purchase_order_count = $purchaseOrderCount;
        $userDetails->purchase_return_count = $purchaseReturnOrderCount;
        $userDetails->sales_order_count = $salesOrderCount;
        $userDetails->sales_return_count = $salesReturnOrderCount;

        $userDetails->total_amount = $userTotalOrderAmount;


        if ($userDetails->opening_balance_type == "receive") {
            $userDetails->paid_amount = $userTotalPaidPayment - $userDetails->opening_balance;
        } else {
            $userDetails->paid_amount = $userTotalPaidPayment + $userDetails->opening_balance;
        }

        $userDetails->due_amount = $userDetails->total_amount - $userDetails->paid_amount;
        $userDetails->save();
    }

    public static function uploadFile($request): array
    {
        $folder = $request->folder;
        $folderString = "";

        if ($folder == "user") {
            $folderString = "userImagePath";
        } else if ($folder == "company") {
            $folderString = "companyLogoPath";
        } else if ($folder == "brand") {
            $folderString = "brandImagePath";
        } else if ($folder == "category") {
            $folderString = "categoryImagePath";
        } else if ($folder == "product") {
            $folderString = "productImagePath";
        } else if ($folder == "banners") {
            $folderString = "frontBannerPath";
        } else if ($folder == "langs") {
            $folderString = "langImagePath";
        } else if ($folder == "expenses") {
            $folderString = "expenseBillPath";
        } else if ($folder == "warehouses") {
            $folderString = "warehouseLogoPath";
        }

        $folderPath = self::getFolderPath($folderString);

        if ($request->hasFile('image') || $request->hasFile('file')) {
            $largeLogo = $request->hasFile('image') ? $request->file('image') : $request->file('file');

            $fileName = $folder . '_' . strtolower(Str::random(20)) . '.' . $largeLogo->getClientOriginalExtension();
            $largeLogo->storePubliclyAs($folderPath, $fileName);
        }

        /** @noinspection PhpUndefinedVariableInspection */
        return [
            'file' => $fileName,
            'file_url' => self::getFileUrl($folderPath, $fileName),
        ];
    }

    public static function getFolderPath($type = null)
    {
        $paths = [
            'companyLogoPath' => 'companies',
            'userImagePath' => 'users',
            'expenseBillPath' => 'expenses',
            'brandImagePath' => 'brands',
            'categoryImagePath' => 'categories',
            'productImagePath' => 'products',
            'orderDocumentPath' => 'orders',
            'frontBannerPath' => 'banners',
            'audioFilesPath' => 'audio',
            'langImagePath' => 'langs',
            'warehouseLogoPath' => 'warehouses',
        ];

        return ($type == null) ? $paths : $paths[$type];
    }

    public static function getFileUrl($folderPath, $fileName): string
    {
        if (config('filesystems.default') == 's3') {
            $path = $folderPath . '/' . $fileName;

            return Storage::url($path);
        } else {
            $path = 'uploads/' . $folderPath . '/' . $fileName;

            return asset($path);
        }
    }

    public static function generateInvoiceNumber($orderType): string
    {
        $lastOrderId = Order::max('id');
        $lastOrderId = $lastOrderId + 1;
        $invoiceNumberPreString = "";

        if ($orderType == 'purchases') {
            $invoiceNumberPreString = "P";
        } else if ($orderType == 'sales') {
            $invoiceNumberPreString = "S";
        } else if ($orderType == 'sales-returns') {
            $invoiceNumberPreString = "SR";
        } else if ($orderType == 'purchase-returns') {
            $invoiceNumberPreString = "PR";
        } else if ($orderType == 'payment-in') {
            $invoiceNumberPreString = "PAYIN";
        } else if ($orderType == 'payment-out') {
            $invoiceNumberPreString = "PAYOUT";
        }
        $invoiceNumber = substr(str_repeat(0, 5) . $lastOrderId, -5);

        return $invoiceNumberPreString . '' . $invoiceNumber;
    }

    public static function generateOrderUniqueId(): string
    {
        return Str::random(20);
    }

    public static function getSalesOrderTax($taxRate, $salesPrice, $taxType)
    {
        if ($taxRate != 0) {
            if ($taxType == 'exclusive') {
                $taxAmount = ($salesPrice * ($taxRate / 100));
            } else {
                $singleUnitPrice = ($salesPrice * 100) / (100 + $taxRate);
                $taxAmount = ($singleUnitPrice) * ($taxRate / 100);
            }
            return $taxAmount;
        } else {
            return 0;
        }
    }

    public static function getSalesPriceWithTax($taxRate, $salesPrice, $taxType)
    {
        if ($taxType == 'exclusive') {
            $taxAmount = ($salesPrice * ($taxRate / 100));
            return $salesPrice + $taxAmount;
        } else {
            return $salesPrice;
        }
    }

    public static function moduleInformations(): array
    {
        $allModules = Module::all();
        $allEnabledModules = Module::allEnabled();
        $installedModules = [];
        $enabledModules = [];

        foreach ($allModules as $key => $allModule) {
            $modulePath = $allModule->getPath();
            $version = File::get($modulePath . '/version.txt');

            $installedModules[] = [
                'verified_name' => $key,
                'current_version' => preg_replace("/\r|\n/", "", $version)
            ];
        }

        foreach ($allEnabledModules as $allEnabledModuleKey => $allEnabledModule) {
            $enabledModules[] = $allEnabledModuleKey;
        }

        return [
            'installed_modules' => $installedModules,
            'enabled_modules' => $enabledModules,
        ];
    }

    /** @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @throws RelatedResourceNotFoundException
     */
    public static function storeAndUpdateOrder($order, $oldOrderId)
    {
        $request = request();
        $orderType = $order->order_type;
        $actionType = "add";

        // If Order item removed when editing an order
        if ($oldOrderId != "") {
            $actionType = "edit";
            $removedOrderItems = $request->removed_items;
            foreach ($removedOrderItems as $removedOrderItem) {
                $removedItem = OrderItem::find($removedOrderItem);
                // $this->deleteOrderItem($order, $orderType, $removedItem);
                $removedItem->delete();
            }
        }

        $orderSubTotal = 0;
        $totalQuantities = 0;
        if ($request->has('product_items')) {
            $productItems = $request->product_items;

            foreach ($productItems as $productItem) {
                $productItem = (object)$productItem;

                if ($productItem->item_id == '' || $productItem->item_id == null) {
                    $orderItem = new OrderItem();
                    $stockHistoryQuantity = $productItem->quantity;
                    $oldStockQuantity = 0;
                } else {
                    $productItemId = self::getIdFromHash($productItem->item_id);
                    $orderItem = OrderItem::find($productItemId);

                    $stockHistoryQuantity = $orderItem->quantity != $productItem->quantity ? $productItem->quantity : 0;
                    $oldStockQuantity = $orderItem->quantity;
                }

                $orderItem->user_id = self::getHashFromId($order->user_id);
                $orderItem->order_id = $order->xid;
                $orderItem->product_id = $productItem->xid;
                $orderItem->unit_price = $productItem->unit_price;
                $orderItem->unit_id = $productItem->x_unit_id != '' ? $productItem->x_unit_id : null;
                $orderItem->quantity = $productItem->quantity;
                $orderItem->tax_id = isset($productItem->x_tax_id) && $productItem->x_tax_id != '' ? $productItem->x_tax_id : null;
                $orderItem->tax_rate = $productItem->tax_rate;
                $orderItem->discount_rate = $productItem->discount_rate;
                $orderItem->total_discount = $productItem->total_discount;
                $orderItem->total_tax = $productItem->total_tax;
                $orderItem->tax_type = $productItem->tax_type;
                $orderItem->subtotal = $productItem->subtotal;
                $orderItem->single_unit_price = $productItem->single_unit_price;
                $orderItem->save();

                $warehouseId = $order->warehouse_id;
                $productId = $orderItem->product_id;

                // Update warehouse stock for product
                self::recalculateOrderStock($warehouseId, $productId);

                $orderSubTotal += $orderItem->subtotal;
                $totalQuantities += $orderItem->quantity;

                // Tracking Stock History
                if ($stockHistoryQuantity != 0) {
                    $stockHistory = new StockHistory();
                    $stockHistory->warehouse_id = $order->warehouse_id;
                    $stockHistory->product_id = $orderItem->product_id;
                    $stockHistory->quantity = $stockHistoryQuantity;
                    $stockHistory->old_quantity = $oldStockQuantity;
                    $stockHistory->order_type = $order->order_type;
                    $stockHistory->stock_type = $orderType == 'sales' || $orderType == 'purchase-returns' ? 'out' : 'in';
                    $stockHistory->action_type = $actionType;
                    $stockHistory->created_by = auth('api')->user()->id;
                    $stockHistory->save();
                }
            }

            $order->total_items = count($productItems);
        }

        $order->total_quantity = $totalQuantities;
        $order->subtotal = $orderSubTotal;
        $order->due_amount = $orderSubTotal;
        $order->is_deletable = true;

        $order->save();

        // Update Customer or Supplier total amount, due amount, paid amount
        self::updateOrderAmount($order->id);

        return $order;
    }

    public static function getIdFromHash($hash)
    {
        if ($hash != "") {
            $convertedId = Hashids::decode($hash);
            return $convertedId[0];
        }

        return $hash;
    }

    public static function getHashFromId($id): string
    {
        return Hashids::encode($id);
    }

    public static function recalculateOrderStock($warehouseId, $productId)
    {
        $purchaseOrderCount = self::calculateOrderCount('purchases', $warehouseId, $productId);
        $purchaseReturnsOrderCount = self::calculateOrderCount('purchase-returns', $warehouseId, $productId);
        $salesOrderCount = self::calculateOrderCount('sales', $warehouseId, $productId);
        $salesReturnsOrderCount = self::calculateOrderCount('sales-returns', $warehouseId, $productId);

        $addStockAdjustment = StockAdjustment::where('warehouse_id', '=', $warehouseId)
            ->where('product_id', '=', $productId)
            ->where('adjustment_type', '=', 'add')
            ->sum('quantity');
        $subtractStockAdjustment = StockAdjustment::where('warehouse_id', '=', $warehouseId)
            ->where('product_id', '=', $productId)
            ->where('adjustment_type', '=', 'subtract')
            ->sum('quantity');

        $newStockQuantity = $purchaseOrderCount - $salesOrderCount + $salesReturnsOrderCount - $purchaseReturnsOrderCount + $addStockAdjustment - $subtractStockAdjustment;

        // Updating Warehouse Stock
        $productDetails = ProductDetails::withoutGlobalScope('current_warehouse')
            ->where('warehouse_id', '=', $warehouseId)
            ->where('product_id', '=', $productId)
            ->first();
        $currentStock = $newStockQuantity + $productDetails->opening_stock;
        $productDetails->current_stock = $currentStock;

        if ($productDetails->stock_quantitiy_alert != null && $currentStock < $productDetails->stock_quantitiy_alert) {
            $productDetails->status = 'out_of_stock';
        } else {
            $productDetails->status = 'in_stock';
        }

        $productDetails->save();
    }

    public static function calculateOrderCount($orderType, $warehouseId, $productId)
    {
        return OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.warehouse_id', '=', $warehouseId)
            ->where('order_items.product_id', '=', $productId)
            ->where('orders.order_type', '=', $orderType)
            ->sum('order_items.quantity');
    }

    /** @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedFieldInspection
     * @throws RelatedResourceNotFoundException
     */
    public static function updateProductCustomFields($product, $warehouseId)
    {
        $request = request();

        if ($request->has('custom_fields') && count($request->custom_fields) > 0) {
            $customFields = $request->custom_fields;

            foreach ($customFields as $customFieldKey => $customFieldValue) {
                $newCustomField = ProductCustomField::withoutGlobalScope('current_warehouse')
                    ->where('field_name', $customFieldValue)
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if (!$newCustomField) {
                    $newCustomField = new ProductCustomField();
                    $newCustomField->warehouse_id = $warehouseId;
                }

                $newCustomField->product_id = $product->id;
                $newCustomField->field_name = $customFieldKey;
                $newCustomField->field_value = $customFieldValue;
                $newCustomField->save();
            }
        }
    }
}
