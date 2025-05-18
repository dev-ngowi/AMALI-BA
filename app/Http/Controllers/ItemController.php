<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Barcode;
use App\Models\Image;
use App\Models\ItemBarcode;
use App\Models\ItemImage;
use App\Models\ItemUnit;
use App\Models\Stock;
use App\Models\ItemStock;
use App\Models\ItemStore;
use App\Models\ItemCost;
use App\Models\ItemPrice;
use App\Models\ItemTax;
use App\Models\BrandApplicableItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ItemController extends Controller
{
    /**
     * Display a listing of the items.
     */
    public function index()
    {
        try {
            $items = Item::with([
                'category',
                'itemType',
                'itemGroup',
                'itemBarcodes.barcode',
                'itemUnits.buyingUnit',
                'itemUnits.sellingUnit',
                'itemImages.image',
                'brand.brand',
                'itemStores.store',
                'stocks.store',
                'itemStocks.stock',
                'itemCosts.unit',
                'itemPrices.unit',
                'itemTaxes.tax'
            ])->get();

            return response()->json([
                'data' => $items,
                'message' => 'success'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching items: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to fetch items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified item.
     */
    public function show($id)
    {
        try {
            $item = Item::with([
                'category',
                'itemType',
                'itemGroup',
                'itemBarcodes.barcode',
                'itemUnits.buyingUnit',
                'itemUnits.sellingUnit',
                'itemImages.image',
                'brand.brand',
                'itemStores.store',
                'stocks.store',
                'itemStocks.stock',
                'itemCosts.unit',
                'itemPrices.unit',
                'itemTaxes.tax'
            ])->find($id);

            if (!$item) {
                return response()->json([
                    'message' => 'Item not found'
                ], 404);
            }

            return response()->json([
                'data' => $item,
                'message' => 'success'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching item: ' . $e->getMessage(), ['id' => $id, 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to fetch item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created item in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:items,name',
                'category_id' => 'required|exists:categories,id',
                'item_type_id' => 'nullable|exists:item_types,id',
                'item_group_id' => 'nullable|exists:item_groups,id',
                'expire_date' => 'nullable|date',
                'status' => 'nullable|in:active,inactive',
                'barcode' => 'nullable|string|max:255|unique:barcodes,code',
                'item_image_path' => 'nullable|string',
                'item_brand_id' => 'nullable|exists:item_brands,id',
                'buying_unit_id' => 'required|exists:units,id',
                'selling_unit_id' => 'required|exists:units,id',
                'store_data' => 'required|array|min:1',
                'store_data.*.store_id' => 'required|exists:stores,id',
                'store_data.*.min_quantity' => 'nullable|numeric|min:0',
                'store_data.*.max_quantity' => 'nullable|numeric|min:0',
                'store_data.*.stock_quantity' => 'required|numeric|min:0',
                'store_data.*.purchase_rate' => 'required|numeric|min:0',
                'store_data.*.selling_price' => 'required|numeric|min:0',
                'store_data.*.tax_id' => 'nullable|exists:taxes,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for duplicate store_id in store_data
            $storeIds = array_column($request->store_data, 'store_id');
            if (count($storeIds) !== count(array_unique($storeIds))) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['store_data' => ['Duplicate store_id values are not allowed']]
                ], 422);
            }

            return DB::transaction(function () use ($request) {
                $currentTime = Carbon::now();

                // Create item
                $item = Item::create([
                    'name' => $request->name,
                    'category_id' => $request->category_id,
                    'item_type_id' => $request->item_type_id,
                    'item_group_id' => $request->item_group_id,
                    'expire_date' => $request->expire_date,
                    'status' => $request->status ?? 'active',
                    'version' => 1,
                    'last_modified' => $currentTime,
                    'is_synced' => false,
                    'operation' => 'create',
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime
                ]);

                // Create barcode
                if ($request->barcode) {
                    $barcode = Barcode::create([
                        'code' => $request->barcode,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);
                    ItemBarcode::create([
                        'item_id' => $item->id,
                        'barcode_id' => $barcode->id,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);
                }

                // Create image
                if ($request->item_image_path) {
                    $image = Image::create([
                        'file_path' => $request->item_image_path,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);
                    ItemImage::create([
                        'item_id' => $item->id,
                        'image_id' => $image->id,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);
                }

                // Create brand relation
                if ($request->item_brand_id) {
                    BrandApplicableItem::create([
                        'item_id' => $item->id,
                        'brand_id' => $request->item_brand_id,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);
                }

                // Create item units
                ItemUnit::create([
                    'item_id' => $item->id,
                    'buying_unit_id' => $request->buying_unit_id,
                    'selling_unit_id' => $request->selling_unit_id,
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime
                ]);

                // Create store-related data
                foreach ($request->store_data as $storeData) {
                    // Create item_store
                    ItemStore::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);

                    // Create stock
                    $stock = Stock::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'min_quantity' => $storeData['min_quantity'] ?? 0,
                        'max_quantity' => $storeData['max_quantity'] ?? 0,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);

                    // Create item_stock
                    ItemStock::create([
                        'item_id' => $item->id,
                        'stock_id' => $stock->id,
                        'stock_quantity' => $storeData['stock_quantity'],
                        'version' => 1,
                        'last_modified' => $currentTime,
                        'is_synced' => false,
                        'operation' => 'create',
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);

                    // Create item_cost
                    ItemCost::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'unit_id' => $request->buying_unit_id,
                        'amount' => $storeData['purchase_rate'],
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);

                    // Create item_price
                    ItemPrice::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'unit_id' => $request->selling_unit_id,
                        'amount' => $storeData['selling_price'],
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);

                    // Create item_tax
                    if (!empty($storeData['tax_id'])) {
                        ItemTax::create([
                            'item_id' => $item->id,
                            'store_id' => $storeData['store_id'],
                            'tax_id' => $storeData['tax_id'],
                            'created_at' => $currentTime,
                            'updated_at' => $currentTime
                        ]);
                    }
                }

                return response()->json([
                    'data' => $item->load([
                        'category',
                        'itemType',
                        'itemGroup',
                        'itemBarcodes.barcode',
                        'itemUnits.buyingUnit',
                        'itemUnits.sellingUnit',
                        'itemImages.image',
                        'brand.brand',
                        'itemStores.store',
                        'stocks.store',
                        'itemStocks.stock',
                        'itemCosts.unit',
                        'itemPrices.unit',
                        'itemTaxes.tax'
                    ]),
                    'message' => 'Item created successfully'
                ], 201);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            $errorInfo = $e->errorInfo;
            $errorDetails = [
                'sql_state' => $errorInfo[0] ?? 'unknown',
                'error_code' => $errorInfo[1] ?? 'unknown',
                'message' => $errorInfo[2] ?? $e->getMessage(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ];
            Log::error('Database error creating item: ' . json_encode($errorDetails));
            return response()->json([
                'message' => 'Database error',
                'error' => 'Failed to create item: ' . ($errorInfo[2] ?? 'Unknown database issue')
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating item: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to create item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified item in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $item = Item::find($id);
            if (!$item) {
                return response()->json([
                    'message' => 'Item not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:items,name,' . $id,
                'category_id' => 'required|exists:categories,id',
                'item_type_id' => 'nullable|exists:item_types,id',
                'item_group_id' => 'nullable|exists:item_groups,id',
                'expire_date' => 'nullable|date',
                'status' => 'nullable|in:active,inactive',
                'barcode' => 'nullable|string|max:255|unique:barcodes,code',
                'item_image_path' => 'nullable|string',
                'item_brand_id' => 'nullable|exists:item_brands,id',
                'buying_unit_id' => 'required|exists:units,id',
                'selling_unit_id' => 'required|exists:units,id',
                'store_data' => 'required|array|min:1',
                'store_data.*.store_id' => 'required|exists:stores,id',
                'store_data.*.min_quantity' => 'nullable|numeric|min:0',
                'store_data.*.max_quantity' => 'nullable|numeric|min:0',
                'store_data.*.stock_quantity' => 'required|numeric|min:0',
                'store_data.*.purchase_rate' => 'required|numeric|min:0',
                'store_data.*.selling_price' => 'required|numeric|min:0',
                'store_data.*.tax_id' => 'nullable|exists:taxes,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for duplicate store_id in store_data
            $storeIds = array_column($request->store_data, 'store_id');
            if (count($storeIds) !== count(array_unique($storeIds))) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['store_data' => ['Duplicate store_id values are not allowed']]
                ], 422);
            }

            return DB::transaction(function () use ($request, $item) {
                $currentTime = Carbon::now();

                // Update item
                $item->update([
                    'name' => $request->name,
                    'category_id' => $request->category_id,
                    'item_type_id' => $request->item_type_id,
                    'item_group_id' => $request->item_group_id,
                    'expire_date' => $request->expire_date,
                    'status' => $request->status ?? $item->status,
                    'version' => $item->version + 1,
                    'last_modified' => $currentTime,
                    'is_synced' => false,
                    'operation' => 'update',
                    'updated_at' => $currentTime
                ]);

                // Delete existing related records
                $item->itemBarcodes()->delete();
                $item->itemImages()->delete();
                $item->itemUnits()->delete();
                $item->brand()->delete();
                $item->itemStores()->delete();
                $item->stocks()->delete();
                $item->itemStocks()->delete();
                $item->itemCosts()->delete();
                $item->itemPrices()->delete();
                $item->itemTaxes()->delete();

                // Create barcode
                if ($request->barcode) {
                    $barcode = Barcode::firstOrCreate(
                        ['code' => $request->barcode],
                        ['created_at' => $currentTime, 'updated_at' => $currentTime]
                    );
                    ItemBarcode::create([
                        'item_id' => $item->id,
                        'barcode_id' => $barcode->id,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);
                }

                // Create image
                if ($request->item_image_path) {
                    $image = Image::create([
                        'file_path' => $request->item_image_path,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);
                    ItemImage::create([
                        'item_id' => $item->id,
                        'image_id' => $image->id,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);
                }

                // Create brand relation
                if ($request->item_brand_id) {
                    BrandApplicableItem::create([
                        'item_id' => $item->id,
                        'brand_id' => $request->item_brand_id,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);
                }

                // Create item units
                ItemUnit::create([
                    'item_id' => $item->id,
                    'buying_unit_id' => $request->buying_unit_id,
                    'selling_unit_id' => $request->selling_unit_id,
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime
                ]);

                // Create store-related data
                foreach ($request->store_data as $storeData) {
                    // Create item_store
                    ItemStore::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);

                    // Create stock
                    $stock = Stock::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'min_quantity' => $storeData['min_quantity'] ?? 0,
                        'max_quantity' => $storeData['max_quantity'] ?? 0,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);

                    // Create item_stock
                    ItemStock::create([
                        'item_id' => $item->id,
                        'stock_id' => $stock->id,
                        'stock_quantity' => $storeData['stock_quantity'],
                        'version' => 1,
                        'last_modified' => $currentTime,
                        'is_synced' => false,
                        'operation' => 'create',
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);

                    // Create item_cost
                    ItemCost::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'unit_id' => $request->buying_unit_id,
                        'amount' => $storeData['purchase_rate'],
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);

                    // Create item_price
                    ItemPrice::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'unit_id' => $request->selling_unit_id,
                        'amount' => $storeData['selling_price'],
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ]);

                    // Create item_tax
                    if (!empty($storeData['tax_id'])) {
                        ItemTax::create([
                            'item_id' => $item->id,
                            'store_id' => $storeData['store_id'],
                            'tax_id' => $storeData['tax_id'],
                            'created_at' => $currentTime,
                            'updated_at' => $currentTime
                        ]);
                    }
                }

                return response()->json([
                    'data' => $item->load([
                        'category',
                        'itemType',
                        'itemGroup',
                        'itemBarcodes.barcode',
                        'itemUnits.buyingUnit',
                        'itemUnits.sellingUnit',
                        'itemImages.image',
                        'brand.brand',
                        'itemStores.store',
                        'stocks.store',
                        'itemStocks.stock',
                        'itemCosts.unit',
                        'itemPrices.unit',
                        'itemTaxes.tax'
                    ]),
                    'message' => 'Item updated successfully'
                ], 200);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            $errorInfo = $e->errorInfo;
            $errorDetails = [
                'sql_state' => $errorInfo[0] ?? 'unknown',
                'error_code' => $errorInfo[1] ?? 'unknown',
                'message' => $errorInfo[2] ?? $e->getMessage(),
                'id' => $id,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ];
            Log::error('Database error updating item: ' . json_encode($errorDetails));
            return response()->json([
                'message' => 'Database error',
                'error' => 'Failed to update item: ' . ($errorInfo[2] ?? 'Unknown database issue')
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating item: ' . $e->getMessage(), [
                'id' => $id,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to update item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified item from storage.
     */
    public function destroy($id)
    {
        try {
            $item = Item::find($id);
            if (!$item) {
                return response()->json([
                    'message' => 'Item not found'
                ], 404);
            }

            // Check for dependencies
            if ($item->itemStocks()->exists() || DB::table('cart_items')->where('item_id', $id)->exists()) {
                return response()->json([
                    'message' => 'Cannot delete item because it is referenced in cart items or stocks'
                ], 422);
            }

            DB::transaction(function () use ($item) {
                // Delete related records
                $item->itemBarcodes()->delete();
                $item->itemImages()->delete();
                $item->itemUnits()->delete();
                $item->brand()->delete();
                $item->itemStores()->delete();
                $item->stocks()->delete();
                $item->itemStocks()->delete();
                $item->itemCosts()->delete();
                $item->itemPrices()->delete();
                $item->itemTaxes()->delete();
                $item->delete();
            });

            return response()->json([
                'message' => 'Item deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            $errorInfo = $e->errorInfo;
            $errorDetails = [
                'sql_state' => $errorInfo[0] ?? 'unknown',
                'error_code' => $errorInfo[1] ?? 'unknown',
                'message' => $errorInfo[2] ?? $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString()
            ];
            Log::error('Database error deleting item: ' . json_encode($errorDetails));
            return response()->json([
                'message' => 'Cannot delete item due to existing references',
                'error' => $errorInfo[2] ?? 'Unknown database issue'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error deleting item: ' . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to delete item: ' . $e->getMessage()
            ], 500);
        }
    }
}