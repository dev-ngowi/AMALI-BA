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
use App\Models\Brand; // Added Brand model for correct validation
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ItemController extends Controller
{
    /**
     * Display a listing of the items with pagination and search.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $searchQuery = $request->input('searchQuery');

            $query = Item::query()->with([
                'category',
                'itemType',
                'itemGroup',
                'itemBarcodes.barcode',
                'itemUnits.buyingUnit',
                'itemUnits.sellingUnit',
                'itemImages.image',
                'brand.brand', // Assuming 'brand' is a hasOne/hasMany through BrandApplicableItem to Brand
                'itemStores.store',
                'stocks.store',
                'itemStocks.stock',
                'itemCosts.unit',
                'itemPrices.unit',
                'itemTaxes.tax'
            ]);

            if ($searchQuery) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchQuery) . '%'])
                      ->orWhereHas('itemBarcodes.barcode', function ($q) use ($searchQuery) {
                          // Make sure 'barcode' relationship exists on ItemBarcode model and 'code' column on Barcode model
                          $q->whereRaw('LOWER(code) LIKE ?', ['%' . strtolower($searchQuery) . '%']);
                      });
                });
            }

            $items = $query->paginate($perPage);

            Log::info('Fetched items', [
                // 'query' => $query->toSql(), // Commented out for production readiness, uncomment for debugging
                // 'bindings' => $query->getBindings(), // Commented out for production readiness, uncomment for debugging
                'count' => $items->total(),
                'page' => $page,
                'per_page' => $perPage
            ]);

            return response()->json([
                'data' => $items->items(),
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
                'message' => 'Items retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching items: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to fetch items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified item.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
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
                'itemStores.store', // Removed redundant 'itemStores.store'
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
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:items,name',
                'category_id' => 'required|exists:categories,id',
                'item_type_id' => 'nullable|exists:item_types,id',
                'item_group_id' => 'nullable|exists:item_groups,id',
                'expire_date' => 'nullable|date',
                'status' => 'nullable|in:active,inactive',
                'barcode' => 'nullable|string|max:255|unique:barcodes,code', // 'unique' check applied only if barcode is present
                'item_image_path' => 'nullable|string',
                'item_brand_id' => 'nullable|exists:brands,id', // Validating against 'brands' table
                'buying_unit_id' => 'required|exists:units,id',
                'selling_unit_id' => 'required|exists:units,id',
                'store_data' => 'required|array|min:1',
                'store_data.*.store_id' => 'required|exists:stores,id',
                'store_data.*.min_quantity' => 'nullable|numeric|min:0',
                'store_data.*.max_quantity' => 'nullable|numeric|min:0',
                'store_data.*.stock_quantity' => 'required|numeric|min:0',
                'store_data.*.purchase_rate' => 'required|numeric|min:0.01',
                'store_data.*.selling_price' => 'required|numeric|min:0.01',
                'store_data.*.tax_id' => 'nullable|exists:taxes,id',
                // 'created_at' => 'nullable|date', // Let Eloquent handle timestamps
                // 'updated_at' => 'nullable|date'  // Let Eloquent handle timestamps
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $storeIds = array_column($request->store_data, 'store_id');
            if (count($storeIds) !== count(array_unique($storeIds))) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['store_data' => ['Duplicate store_id values are not allowed for a single item']]
                ], 422);
            }

            return DB::transaction(function () use ($request) {
                // $currentTime = Carbon::now(); // Let Eloquent handle timestamps

                $item = Item::create([
                    'name' => $request->name,
                    'category_id' => $request->category_id,
                    'item_type_id' => $request->item_type_id,
                    'item_group_id' => $request->item_group_id,
                    'expire_date' => $request->expire_date,
                    'status' => $request->status ?? 'active',
                    'version' => 1,
                    'last_modified' => Carbon::now(), // Use Carbon::now() directly
                    'is_synced' => false,
                    'operation' => 'create',
                    // 'created_at' => $request->created_at ?? $currentTime, // Let Eloquent handle timestamps
                    // 'updated_at' => $request->updated_at ?? $currentTime  // Let Eloquent handle timestamps
                ]);

                if ($request->barcode) {
                    $barcode = Barcode::create([
                        'code' => $request->barcode,
                        // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                    ]);
                    ItemBarcode::create([
                        'item_id' => $item->id,
                        'barcode_id' => $barcode->id,
                        // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                    ]);
                }

                if ($request->item_image_path) {
                    $image = Image::create([
                        'file_path' => $request->item_image_path,
                        // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                    ]);
                    ItemImage::create([
                        'item_id' => $item->id,
                        'image_id' => $image->id,
                        // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                    ]);
                }

                if ($request->item_brand_id) {
                    BrandApplicableItem::create([
                        'item_id' => $item->id,
                        'brand_id' => $request->item_brand_id, // This refers to the 'id' of the 'brands' table
                        // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                    ]);
                }

                ItemUnit::create([
                    'item_id' => $item->id,
                    'buying_unit_id' => $request->buying_unit_id,
                    'selling_unit_id' => $request->selling_unit_id,
                    // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                    // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                ]);

                foreach ($request->store_data as $storeData) {
                    ItemStore::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                    ]);

                    $stock = Stock::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'min_quantity' => $storeData['min_quantity'] ?? 0,
                        'max_quantity' => $storeData['max_quantity'] ?? 0,
                        // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                    ]);

                    ItemStock::create([
                        'item_id' => $item->id,
                        'stock_id' => $stock->id,
                        'stock_quantity' => $storeData['stock_quantity'],
                        'version' => 1,
                        'last_modified' => Carbon::now(), // Use Carbon::now() directly
                        'is_synced' => false,
                        'operation' => 'create',
                        // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                    ]);

                    ItemCost::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'unit_id' => $request->buying_unit_id,
                        'amount' => $storeData['purchase_rate'],
                        // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                    ]);

                    ItemPrice::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'unit_id' => $request->selling_unit_id,
                        'amount' => $storeData['selling_price'],
                        // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                    ]);

                    if (!empty($storeData['tax_id'])) {
                        ItemTax::create([
                            'item_id' => $item->id,
                            'store_id' => $storeData['store_id'],
                            'tax_id' => $storeData['tax_id'],
                            // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                            // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
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
     * Store multiple items in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeBatch(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'items' => 'required|array|min:1',
                'items.*.name' => 'required|string|max:255|unique:items,name',
                'items.*.category_id' => 'required|exists:categories,id',
                'items.*.item_type_id' => 'nullable|exists:item_types,id',
                'items.*.item_group_id' => 'nullable|exists:item_groups,id',
                'items.*.expire_date' => 'nullable|date',
                'items.*.status' => 'nullable|in:active,inactive',
                'items.*.barcode' => 'nullable|string|max:255|unique:barcodes,code', // 'unique' check applied only if barcode is present
                'items.*.item_image_path' => 'nullable|string',
                'items.*.item_brand_id' => 'nullable|exists:brands,id', // Validating against 'brands' table
                'items.*.buying_unit_id' => 'required|exists:units,id',
                'items.*.selling_unit_id' => 'required|exists:units,id',
                'items.*.store_data' => 'required|array|min:1',
                'items.*.store_data.*.store_id' => 'required|exists:stores,id',
                'items.*.store_data.*.min_quantity' => 'nullable|numeric|min:0',
                'items.*.store_data.*.max_quantity' => 'nullable|numeric|min:0',
                'items.*.store_data.*.stock_quantity' => 'required|numeric|min:0', // Corrected validation path
                'items.*.store_data.*.purchase_rate' => 'required|numeric|min:0.01',
                'items.*.store_data.*.selling_price' => 'required|numeric|min:0.01',
                'items.*.store_data.*.tax_id' => 'nullable|exists:taxes,id',
                // 'items.*.created_at' => 'nullable|date', // Let Eloquent handle timestamps
                // 'items.*.updated_at' => 'nullable|date'  // Let Eloquent handle timestamps
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $itemsToCreate = $request->items;
            $createdItems = [];

            // Collect all barcodes from the request to check for uniqueness within the batch
            $allBarcodesInRequest = [];
            foreach ($itemsToCreate as $itemData) {
                if (!empty($itemData['barcode'])) {
                    $allBarcodesInRequest[] = $itemData['barcode'];
                }
            }

            if (count($allBarcodesInRequest) !== count(array_unique($allBarcodesInRequest))) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['items.*.barcode' => ['Duplicate barcode values are not allowed within the batch']]
                ], 422);
            }


            return DB::transaction(function () use ($itemsToCreate, &$createdItems) {
                // $currentTime = Carbon::now(); // Let Eloquent handle timestamps

                foreach ($itemsToCreate as $itemData) {
                    $storeIds = array_column($itemData['store_data'], 'store_id');
                    if (count($storeIds) !== count(array_unique($storeIds))) {
                        throw new \Exception("Duplicate store_id values in store_data for item {$itemData['name']}");
                    }

                    $item = Item::create([
                        'name' => $itemData['name'],
                        'category_id' => $itemData['category_id'],
                        'item_type_id' => $itemData['item_type_id'] ?? null,
                        'item_group_id' => $itemData['item_group_id'] ?? null,
                        'expire_date' => $itemData['expire_date'] ?? null,
                        'status' => $itemData['status'] ?? 'active',
                        'version' => 1,
                        'last_modified' => Carbon::now(),
                        'is_synced' => false,
                        'operation' => 'create',
                        // 'created_at' => $itemData['created_at'] ?? $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $itemData['updated_at'] ?? $currentTime  // Let Eloquent handle timestamps
                    ]);

                    if (!empty($itemData['barcode'])) {
                        $barcode = Barcode::create([
                            'code' => $itemData['barcode'],
                            // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                            // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                        ]);
                        ItemBarcode::create([
                            'item_id' => $item->id,
                            'barcode_id' => $barcode->id,
                            // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                            // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                        ]);
                    }

                    if (!empty($itemData['item_image_path'])) {
                        $image = Image::create([
                            'file_path' => $itemData['item_image_path'],
                            // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                            // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                        ]);
                        ItemImage::create([
                            'item_id' => $item->id,
                            'image_id' => $image->id,
                            // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                            // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                        ]);
                    }

                    if (!empty($itemData['item_brand_id'])) {
                        BrandApplicableItem::create([
                            'item_id' => $item->id,
                            'brand_id' => $itemData['item_brand_id'],
                            // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                            // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                        ]);
                    }

                    ItemUnit::create([
                        'item_id' => $item->id,
                        'buying_unit_id' => $itemData['buying_unit_id'],
                        'selling_unit_id' => $itemData['selling_unit_id'],
                        // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                        // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                    ]);

                    foreach ($itemData['store_data'] as $storeData) {
                        ItemStore::create([
                            'item_id' => $item->id,
                            'store_id' => $storeData['store_id'],
                            // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                            // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                        ]);

                        $stock = Stock::create([
                            'item_id' => $item->id,
                            'store_id' => $storeData['store_id'],
                            'min_quantity' => $storeData['min_quantity'] ?? 0,
                            'max_quantity' => $storeData['max_quantity'] ?? 0,
                            // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                            // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                        ]);

                        ItemStock::create([
                            'item_id' => $item->id,
                            'stock_id' => $stock->id,
                            'stock_quantity' => $storeData['stock_quantity'],
                            'version' => 1,
                            'last_modified' => Carbon::now(),
                            'is_synced' => false,
                            'operation' => 'create',
                            // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                            // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                        ]);

                        ItemCost::create([
                            'item_id' => $item->id,
                            'store_id' => $storeData['store_id'],
                            'unit_id' => $itemData['buying_unit_id'],
                            'amount' => $storeData['purchase_rate'],
                            // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                            // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                        ]);

                        ItemPrice::create([
                            'item_id' => $item->id,
                            'store_id' => $storeData['store_id'],
                            'unit_id' => $itemData['selling_unit_id'],
                            'amount' => $storeData['selling_price'],
                            // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                            // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                        ]);

                        if (!empty($storeData['tax_id'])) {
                            ItemTax::create([
                                'item_id' => $item->id,
                                'store_id' => $storeData['store_id'],
                                'tax_id' => $storeData['tax_id'],
                                // 'created_at' => $currentTime, // Let Eloquent handle timestamps
                                // 'updated_at' => $currentTime  // Let Eloquent handle timestamps
                            ]);
                        }
                    }

                    $createdItems[] = $item->load([
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
                    ]);
                }

                return response()->json([
                    'data' => $createdItems,
                    'message' => 'Items created successfully'
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
            Log::error('Database error creating batch items: ' . json_encode($errorDetails));
            return response()->json([
                'message' => 'Database error',
                'error' => 'Failed to create items: ' . ($errorInfo[2] ?? 'Unknown database issue')
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating batch items: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to create items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified item in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
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
                'barcode' => 'nullable|string|max:255|unique:barcodes,code,' . ($item->itemBarcodes->first()->barcode->id ?? 'NULL') . ',id', // Handle existing barcode for unique rule
                'item_image_path' => 'nullable|string',
                'item_brand_id' => 'nullable|exists:brands,id',
                'buying_unit_id' => 'required|exists:units,id',
                'selling_unit_id' => 'required|exists:units,id',
                'store_data' => 'required|array|min:1',
                'store_data.*.store_id' => 'required|exists:stores,id',
                'store_data.*.min_quantity' => 'nullable|numeric|min:0',
                'store_data.*.max_quantity' => 'nullable|numeric|min:0',
                'store_data.*.stock_quantity' => 'required|numeric|min:0',
                'store_data.*.purchase_rate' => 'required|numeric|min:0.01',
                'store_data.*.selling_price' => 'required|numeric|min:0.01',
                'store_data.*.tax_id' => 'nullable|exists:taxes,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $storeIds = array_column($request->store_data, 'store_id');
            if (count($storeIds) !== count(array_unique($storeIds))) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['store_data' => ['Duplicate store_id values are not allowed for a single item']]
                ], 422);
            }

            return DB::transaction(function () use ($request, $item) {
                $currentTime = Carbon::now();

                $item->update([
                    'name' => $request->name,
                    'category_id' => $request->category_id,
                    'item_type_id' => $request->item_type_id,
                    'item_group_id' => $request->item_group_id,
                    'expire_date' => $request->expire_date,
                    'status' => $request->status ?? 'active',
                    'version' => $item->version + 1, // Increment version on update
                    'last_modified' => $currentTime,
                    'is_synced' => false,
                    'operation' => 'update',
                    // 'updated_at' => $currentTime // Eloquent handles this automatically
                ]);

                // Handle barcode update
                if ($request->has('barcode')) {
                    $itemBarcode = $item->itemBarcodes->first();
                    if ($itemBarcode) {
                        // Update existing barcode
                        $barcode = Barcode::find($itemBarcode->barcode_id);
                        if ($barcode) {
                            $barcode->update(['code' => $request->barcode]);
                        } else {
                            // Barcode link exists but actual barcode record is missing, create new
                            $newBarcode = Barcode::create(['code' => $request->barcode]);
                            $itemBarcode->update(['barcode_id' => $newBarcode->id]);
                        }
                    } elseif ($request->barcode) {
                        // No existing barcode link, create new barcode and link
                        $newBarcode = Barcode::create(['code' => $request->barcode]);
                        ItemBarcode::create([
                            'item_id' => $item->id,
                            'barcode_id' => $newBarcode->id,
                        ]);
                    }
                } else {
                    // If barcode is not provided in request, delete existing barcode link
                    $item->itemBarcodes()->delete();
                }

                // Handle item image update
                if ($request->has('item_image_path')) {
                    $itemImage = $item->itemImages->first();
                    if ($itemImage) {
                        $image = Image::find($itemImage->image_id);
                        if ($image) {
                            $image->update(['file_path' => $request->item_image_path]);
                        } else {
                            $newImage = Image::create(['file_path' => $request->item_image_path]);
                            $itemImage->update(['image_id' => $newImage->id]);
                        }
                    } elseif ($request->item_image_path) {
                        $newImage = Image::create(['file_path' => $request->item_image_path]);
                        ItemImage::create([
                            'item_id' => $item->id,
                            'image_id' => $newImage->id,
                        ]);
                    }
                } else {
                    $item->itemImages()->delete();
                }


                // Handle brand update
                if ($request->has('item_brand_id')) {
                    $brandApplicableItem = $item->brand; // Assuming 'brand' relation gives BrandApplicableItem directly
                    if ($brandApplicableItem) {
                        $brandApplicableItem->update(['brand_id' => $request->item_brand_id]);
                    } else {
                        BrandApplicableItem::create([
                            'item_id' => $item->id,
                            'brand_id' => $request->item_brand_id,
                        ]);
                    }
                } else {
                    $item->brand()->delete();
                }

                // Update ItemUnit (assuming one-to-one or always update the first one)
                $itemUnit = $item->itemUnits->first();
                if ($itemUnit) {
                    $itemUnit->update([
                        'buying_unit_id' => $request->buying_unit_id,
                        'selling_unit_id' => $request->selling_unit_id,
                    ]);
                } else {
                    ItemUnit::create([
                        'item_id' => $item->id,
                        'buying_unit_id' => $request->buying_unit_id,
                        'selling_unit_id' => $request->selling_unit_id,
                    ]);
                }

                // Handle store-specific data (stocks, costs, prices, taxes)
                // This is a more complex update, requiring careful handling of existing vs. new records.
                // A common strategy is to delete old ones and recreate, or diff and update/create/delete.
                // For simplicity and based on the provided "store_data" structure, I'll demonstrate
                // a "delete all and recreate" approach for simplicity, but for large datasets,
                // a diffing approach is more efficient.

                $item->itemStores()->delete();
                $item->stocks()->delete(); // Deletes associated ItemStock records due to foreign key constraints and onDelete('cascade') if set
                $item->itemCosts()->delete();
                $item->itemPrices()->delete();
                $item->itemTaxes()->delete();

                foreach ($request->store_data as $storeData) {
                    ItemStore::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                    ]);

                    $stock = Stock::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'min_quantity' => $storeData['min_quantity'] ?? 0,
                        'max_quantity' => $storeData['max_quantity'] ?? 0,
                    ]);

                    ItemStock::create([
                        'item_id' => $item->id,
                        'stock_id' => $stock->id,
                        'stock_quantity' => $storeData['stock_quantity'],
                        'version' => 1, // Reset version for new stock entry or manage incrementally
                        'last_modified' => $currentTime,
                        'is_synced' => false,
                        'operation' => 'create', // Or 'update' if you're diffing
                    ]);

                    ItemCost::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'unit_id' => $request->buying_unit_id,
                        'amount' => $storeData['purchase_rate'],
                    ]);

                    ItemPrice::create([
                        'item_id' => $item->id,
                        'store_id' => $storeData['store_id'],
                        'unit_id' => $request->selling_unit_id,
                        'amount' => $storeData['selling_price'],
                    ]);

                    if (!empty($storeData['tax_id'])) {
                        ItemTax::create([
                            'item_id' => $item->id,
                            'store_id' => $storeData['store_id'],
                            'tax_id' => $storeData['tax_id'],
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
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $id
            ];
            Log::error('Database error updating item: ' . json_encode($errorDetails));
            return response()->json([
                'message' => 'Database error',
                'error' => 'Failed to update item: ' . ($errorInfo[2] ?? 'Unknown database issue')
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating item: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $id
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to update item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified item from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
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

            DB::transaction(function () use ($item) {
                // Delete related records first to avoid foreign key constraints issues
                // Assuming cascade deletes are not fully configured or for explicit control
                $item->itemBarcodes()->delete();
                $item->itemImages()->delete();
                $item->itemUnits()->delete();
                $item->brand()->delete(); // Assuming 'brand' relation is for BrandApplicableItem
                $item->itemStores()->delete();
                $item->stocks()->delete(); // This should also delete item_stocks if cascade is set
                $item->itemCosts()->delete();
                $item->itemPrices()->delete();
                $item->itemTaxes()->delete();

                $item->delete();
            });

            return response()->json([
                'message' => 'Item deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting item: ' . $e->getMessage(), ['id' => $id, 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to delete item: ' . $e->getMessage()
            ], 500);
        }
    }
}