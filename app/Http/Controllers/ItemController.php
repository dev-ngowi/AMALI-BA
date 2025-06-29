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
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ItemController extends Controller
{
    
    public function getItemById($id)
    {
        try {
            $defaultStoreId = 1; // Match the Python function's default store_id

            $item = Item::whereNull('deleted_at')->find($id);

            if (!$item) {
                Log::info('Item not found', ['id' => $id]);
                return response()->json([
                    'message' => 'Item not found'
                ], 404);
            }

            $itemUnit = ItemUnit::where('item_id', $id)
                ->leftJoin('units', 'item_units.selling_unit_id', '=', 'units.id')
                ->select('units.name as item_unit')
                ->first();

            $itemPrice = ItemPrice::where('item_id', $id)
                ->where('store_id', $defaultStoreId)
                ->first();

            $itemStock = ItemStock::where('item_id', $id)
                ->join('stocks', 'item_stocks.stock_id', '=', 'stocks.id')
                ->where('stocks.store_id', $defaultStoreId)
                ->select('item_stocks.stock_quantity')
                ->first();

            $data = [
                'item_name' => $item->name,
                'item_unit' => $itemUnit ? $itemUnit->item_unit : 'Unit',
                'item_price' => $itemPrice ? floatval($itemPrice->amount) : 0.0,
                'stock_quantity' => $itemStock ? floatval($itemStock->stock_quantity) : 0.0,
            ];

            Log::info('Fetched item by ID', [
                'id' => $id,
                'data' => $data
            ]);

            return response()->json([
                'data' => $data,
                'message' => 'Item retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching item by ID: ' . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to fetch item: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function indexSaleItems(Request $request)
    {
        try {
            $searchQuery = $request->input('searchQuery');
            $ids = $request->input('ids') ? explode(',', $request->input('ids')) : null;
            $storeId = $request->input('store_id', 1); // Default store_id

            $query = Item::query()->with([
                'itemUnits.sellingUnit',
                'itemPrices' => function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                },
                'itemStocks' => function ($q) use ($storeId) {
                    $q->join('stocks', 'item_stocks.stock_id', '=', 'stocks.id')
                      ->where('stocks.store_id', $storeId)
                      ->select('item_stocks.*');
                },
                'itemImages.image'
            ])->whereNull('deleted_at');

            if ($ids) {
                $query->whereIn('id', $ids);
            } elseif ($searchQuery) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchQuery) . '%'])
                      ->orWhereHas('itemBarcodes.barcode', function ($q) use ($searchQuery) {
                          $q->whereRaw('LOWER(code) LIKE ?', ['%' . strtolower($searchQuery) . '%']);
                      });
                });
            } else {
                $query->take(10); // Limit to 10 items by default
            }

            $items = $query->get();

            $data = $items->map(function ($item) use ($storeId) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'item_prices' => $item->itemPrices->map(function ($price) {
                        return [
                            'amount' => floatval($price->amount),
                            'store_id' => $price->store_id,
                            'unit_id' => $price->unit_id
                        ];
                    })->toArray(),
                    'item_stocks' => $item->itemStocks->map(function ($stock) {
                        return [
                            'stock_quantity' => floatval($stock->stock_quantity),
                            'stock_id' => $stock->stock_id
                        ];
                    })->toArray(),
                    'item_units' => [
                        'selling_unit' => $item->itemUnits->first() && $item->itemUnits->first()->sellingUnit
                            ? ['name' => $item->itemUnits->first()->sellingUnit->name]
                            : ['name' => 'Unit']
                    ],
                    'item_images' => $item->itemImages->map(function ($itemImage) {
                        return [
                            'file_path' => $itemImage->image ? $itemImage->image->file_path : null
                        ];
                    })->toArray()
                ];
            });

            Log::info('Fetched items for sales', [
                'count' => $items->count(),
                'ids' => $ids,
                'searchQuery' => $searchQuery,
                'store_id' => $storeId
            ]);

            return response()->json([
                'data' => $data,
                'message' => 'Items retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching items for sales: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to fetch items: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 50);
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
                'brand.brand',
                'itemStores.store',
                'stocks.store',
                'itemStocks.stock',
                'itemCosts.unit',
                'itemPrices.unit',
                'itemTaxes.tax'
            ])->whereNull('deleted_at');

            if ($searchQuery) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchQuery) . '%'])
                      ->orWhereHas('itemBarcodes.barcode', function ($q) use ($searchQuery) {
                          $q->whereRaw('LOWER(code) LIKE ?', ['%' . strtolower($searchQuery) . '%']);
                      });
                });
            }

            $items = $query->paginate($perPage);

            Log::info('Fetched items', [
                'count' => $items->total(),
                'page' => $page,
                'per_page' => $perPage,
                'request' => $request->all()
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
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to fetch items: ' . $e->getMessage()
            ], 500);
        }
    }

   public function show($id)
    {
        try {
            $storeId = request()->input('store_id', 1); // Default store_id

            $item = Item::with([
                'itemUnits.sellingUnit',
                'itemPrices' => function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                },
                'itemStocks' => function ($q) use ($storeId) {
                    $q->join('stocks', 'item_stocks.stock_id', '=', 'stocks.id')
                      ->where('stocks.store_id', $storeId)
                      ->select('item_stocks.*');
                },
                'itemImages.image'
            ])->whereNull('deleted_at')->find($id);

            if (!$item) {
                Log::info('Item not found', ['id' => $id]);
                return response()->json([
                    'message' => 'Item not found'
                ], 404);
            }

            $data = [
                'id' => $item->id,
                'name' => $item->name,
                'item_prices' => $item->itemPrices->map(function ($price) {
                    return [
                        'amount' => floatval($price->amount),
                        'store_id' => $price->store_id,
                        'unit_id' => $price->unit_id
                    ];
                })->toArray(),
                'item_stocks' => $item->itemStocks->map(function ($stock) {
                    return [
                        'stock_quantity' => floatval($stock->stock_quantity),
                        'stock_id' => $stock->stock_id
                    ];
                })->toArray(),
                'item_units' => [
                    'selling_unit' => $item->itemUnits->first() && $item->itemUnits->first()->sellingUnit
                        ? ['name' => $item->itemUnits->first()->sellingUnit->name]
                        : ['name' => 'Unit']
                ],
                'item_images' => $item->itemImages->map(function ($itemImage) {
                    return [
                        'file_path' => $itemImage->image ? $itemImage->image->file_path : null
                    ];
                })->toArray()
            ];

            Log::info('Fetched item for sales', [
                'id' => $id,
                'store_id' => $storeId
            ]);

            return response()->json([
                'data' => $data,
                'message' => 'Item retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching item: ' . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString(),
                'request' => request()->all()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to fetch item: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get details for a specific item and store combination.
     * This includes buying_unit_id, purchase_rate, selling_price, and unit name.
     *
     * @param  int  $itemId
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getItemDetails($itemId, Request $request)
    {
        try {
            $storeId = $request->query('store_id');

            if (!$storeId) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['store_id' => ['The store_id parameter is required.']]
                ], 422);
            }

            // Find the item
            $item = Item::whereNull('deleted_at')->find($itemId);

            if (!$item) {
                return response()->json([
                    'message' => 'Item not found'
                ], 404);
            }

            // Get the buying unit ID and name from ItemUnit table
            $itemUnit = ItemUnit::where('item_id', $itemId)
                ->leftJoin('units', 'item_units.buying_unit_id', '=', 'units.id')
                ->select('item_units.buying_unit_id', 'units.name as buying_unit_name')
                ->first();

            $buyingUnitId = $itemUnit ? $itemUnit->buying_unit_id : null;
            $buyingUnitName = $itemUnit ? $itemUnit->buying_unit_name : null;

            // Get the purchase rate (cost) for the specific item and store
            $itemCost = ItemCost::where('item_id', $itemId)
                                ->where('store_id', $storeId)
                                ->first();
            $purchaseRate = $itemCost ? $itemCost->amount : 0;

            // Get the selling price for the specific item and store
            $itemPrice = ItemPrice::where('item_id', $itemId)
                                  ->where('store_id', $storeId)
                                  ->first();
            $sellingPrice = $itemPrice ? $itemPrice->amount : 0;

            if (is_null($buyingUnitId) && $purchaseRate === 0 && $sellingPrice === 0) {
                return response()->json([
                    'message' => 'No specific details found for this item in the selected store.',
                    'data' => null
                ], 404);
            }

            Log::info('Fetched item details for PO form', [
                'item_id' => $itemId,
                'store_id' => $storeId,
                'buying_unit_id' => $buyingUnitId,
                'buying_unit_name' => $buyingUnitName,
                'purchase_rate' => $purchaseRate,
                'selling_price' => $sellingPrice
            ]);

            return response()->json([
                'data' => [
                    'buying_unit_id' => $buyingUnitId,
                    'buying_unit_name' => $buyingUnitName,
                    'purchase_rate' => $purchaseRate,
                    'selling_price' => $sellingPrice,
                ],
                'message' => 'Item details retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching item details: ' . $e->getMessage(), [
                'item_id' => $itemId,
                'store_id' => $request->query('store_id'),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to fetch item details: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:items,name,NULL,id,deleted_at,NULL',
                'category_id' => 'required|exists:categories,id',
                'item_type_id' => 'nullable|exists:item_types,id',
                'item_group_id' => 'nullable|exists:item_groups,id',
                'expire_date' => 'nullable|date',
                'status' => 'nullable|in:active,inactive',
                'item_barcodes' => 'nullable|array',
                'item_barcodes.*.code' => 'required_with:item_barcodes|string|max:255|unique:barcodes,code',
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
                'created_at' => 'nullable|date',
                'updated_at' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for item creation', [
                    'errors' => $validator->errors()->toArray(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $storeIds = array_column($request->store_data, 'store_id');
            if (count($storeIds) !== count(array_unique($storeIds))) {
                Log::warning('Duplicate store IDs detected in store_data', [
                    'store_ids' => $storeIds,
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['store_data' => ['Duplicate store_id values are not allowed for a single item']]
                ], 422);
            }

            return DB::transaction(function () use ($request) {
                $now = Carbon::now();
                $createdAt = $request->created_at ? Carbon::parse($request->created_at) : $now;
                $updatedAt = $request->updated_at ? Carbon::parse($request->updated_at) : $now;

                $item = Item::create([
                    'name' => $request->name,
                    'category_id' => $request->category_id,
                    'item_type_id' => $request->item_type_id,
                    'item_group_id' => $request->item_group_id ?? 1,
                    'expire_date' => $request->expire_date,
                    'status' => $request->status ?? 'active',
                    'version' => 1,
                    'last_modified' => $now,
                    'is_synced' => true,
                    'operation' => 'create',
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt
                ]);

                if ($request->item_barcodes) {
                    foreach ($request->item_barcodes as $barcodeData) {
                        $barcode = Barcode::create([
                            'code' => $barcodeData['code'],
                            'created_at' => $now,
                            'updated_at' => $now
                        ]);
                        ItemBarcode::create([
                            'item_id' => $item->id,
                            'barcode_id' => $barcode->id,
                            'created_at' => $now,
                            'updated_at' => $now
                        ]);
                    }
                }

                if ($request->item_image_path) {
                    $image = Image::create([
                        'file_path' => $request->item_image_path,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                    ItemImage::create([
                        'item_id' => $item->id,
                        'image_id' => $image->id,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                }

                if ($request->item_brand_id) {
                    BrandApplicableItem::create([
                        'item_id' => $item->id,
                        'brand_id' => $request->item_brand_id,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                }

                ItemUnit::create([
                    'item_id' => $item->id,
                    'buying_unit_id' => $request->buying_unit_id,
                    'selling_unit_id' => $request->selling_unit_id,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);

                foreach ($request->store_data as $storeData) {
                    $storeId = $storeData['store_id'];
                    ItemStore::create([
                        'item_id' => $item->id,
                        'store_id' => $storeId,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);

                    $stock = Stock::create([
                        'item_id' => $item->id,
                        'store_id' => $storeId,
                        'min_quantity' => $storeData['min_quantity'] ?? 0,
                        'max_quantity' => $storeData['max_quantity'] ?? 0,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);

                    ItemStock::create([
                        'item_id' => $item->id,
                        'stock_id' => $stock->id,
                        'stock_quantity' => $storeData['stock_quantity'],
                        'version' => 1,
                        'last_modified' => $now,
                        'is_synced' => true,
                        'operation' => 'create',
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);

                    ItemCost::create([
                        'item_id' => $item->id,
                        'store_id' => $storeId,
                        'unit_id' => $request->buying_unit_id,
                        'amount' => $storeData['purchase_rate'],
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);

                    ItemPrice::create([
                        'item_id' => $item->id,
                        'store_id' => $storeId,
                        'unit_id' => $request->selling_unit_id,
                        'amount' => $storeData['selling_price'],
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);

                    if (!empty($storeData['tax_id'])) {
                        ItemTax::create([
                            'item_id' => $item->id,
                            'store_id' => $storeId,
                            'tax_id' => $storeData['tax_id'],
                            'created_at' => $now,
                            'updated_at' => $now
                        ]);
                    }
                }

                $item->load([
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

                Log::info('Item created successfully', [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'request' => $request->all()
                ]);

                return response()->json([
                    'data' => $item,
                    'message' => 'Item created successfully'
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Error creating item: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to create item: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $item = Item::whereNull('deleted_at')->find($id);
            if (!$item) {
                return response()->json([
                    'message' => 'Item not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:items,name,' . $id . ',id,deleted_at,NULL',
                'category_id' => 'required|exists:categories,id',
                'item_type_id' => 'nullable|exists:item_types,id',
                'item_group_id' => 'nullable|exists:item_groups,id',
                'expire_date' => 'nullable|date',
                'status' => 'nullable|in:active,inactive',
                'item_barcodes' => 'nullable|array',
                'item_barcodes.*.code' => 'required_with:item_barcodes|string|max:255|unique:barcodes,code',
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
                'created_at' => 'nullable|date',
                'updated_at' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for item update', [
                    'item_id' => $id,
                    'errors' => $validator->errors()->toArray(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $storeIds = array_column($request->store_data, 'store_id');
            if (count($storeIds) !== count(array_unique($storeIds))) {
                Log::warning('Duplicate store IDs detected in store_data for update', [
                    'item_id' => $id,
                    'store_ids' => $storeIds,
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['store_data' => ['Duplicate store_id values are not allowed for a single item']]
                ], 422);
            }

            return DB::transaction(function () use ($request, $item) {
                $now = Carbon::now();
                $createdAt = $request->created_at ? Carbon::parse($request->created_at) : $item->created_at;
                $updatedAt = $request->updated_at ? Carbon::parse($request->updated_at) : $now;

                $item->update([
                    'name' => $request->name,
                    'category_id' => $request->category_id,
                    'item_type_id' => $request->item_type_id,
                    'item_group_id' => $request->item_group_id ?? 1,
                    'expire_date' => $request->expire_date,
                    'status' => $request->status ?? 'active',
                    'version' => $item->version + 1,
                    'last_modified' => $now,
                    'is_synced' => true,
                    'operation' => 'update',
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt
                ]);

                if ($request->item_barcodes) {
                    ItemBarcode::where('item_id', $item->id)->delete();
                    Barcode::whereIn('id', ItemBarcode::where('item_id', $item->id)->pluck('barcode_id'))->delete();
                    foreach ($request->item_barcodes as $barcodeData) {
                        $barcode = Barcode::create([
                            'code' => $barcodeData['code'],
                            'created_at' => $now,
                            'updated_at' => $now
                        ]);
                        ItemBarcode::create([
                            'item_id' => $item->id,
                            'barcode_id' => $barcode->id,
                            'created_at' => $now,
                            'updated_at' => $now
                        ]);
                    }
                }

                if ($request->item_image_path) {
                    ItemImage::where('item_id', $item->id)->delete();
                    Image::whereIn('id', ItemImage::where('item_id', $item->id)->pluck('image_id'))->delete();
                    $image = Image::create([
                        'file_path' => $request->item_image_path,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                    ItemImage::create([
                        'item_id' => $item->id,
                        'image_id' => $image->id,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                } else {
                    ItemImage::where('item_id', $item->id)->delete();
                    Image::whereIn('id', ItemImage::where('item_id', $item->id)->pluck('image_id'))->delete();
                }

                if ($request->item_brand_id) {
                    BrandApplicableItem::updateOrCreate(
                        ['item_id' => $item->id],
                        [
                            'brand_id' => $request->item_brand_id,
                            'created_at' => $now,
                            'updated_at' => $now
                        ]
                    );
                } else {
                    BrandApplicableItem::where('item_id', $item->id)->delete();
                }

                ItemUnit::updateOrCreate(
                    ['item_id' => $item->id],
                    [
                        'buying_unit_id' => $request->buying_unit_id,
                        'selling_unit_id' => $request->selling_unit_id,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]
                );

                $existingStoreIds = ItemStore::where('item_id', $item->id)->pluck('store_id')->toArray();
                $newStoreIds = array_column($request->store_data, 'store_id');

                $storesToDelete = array_diff($existingStoreIds, $newStoreIds);
                if ($storesToDelete) {
                    ItemStore::where('item_id', $item->id)->whereIn('store_id', $storesToDelete)->delete();
                    Stock::where('item_id', $item->id)->whereIn('store_id', $storesToDelete)->delete();
                    ItemStock::where('item_id', $item->id)->whereIn('stock_id', Stock::whereIn('store_id', $storesToDelete)->pluck('id'))->delete();
                    ItemCost::where('item_id', $item->id)->whereIn('store_id', $storesToDelete)->delete();
                    ItemPrice::where('item_id', $item->id)->whereIn('store_id', $storesToDelete)->delete();
                    ItemTax::where('item_id', $item->id)->whereIn('store_id', $storesToDelete)->delete();
                }

                foreach ($request->store_data as $storeData) {
                    $storeId = $storeData['store_id'];

                    ItemStore::updateOrCreate(
                        ['item_id' => $item->id, 'store_id' => $storeId],
                        ['created_at' => $now, 'updated_at' => $now]
                    );

                    $stock = Stock::updateOrCreate(
                        ['item_id' => $item->id, 'store_id' => $storeId],
                        [
                            'min_quantity' => $storeData['min_quantity'] ?? 0,
                            'max_quantity' => $storeData['max_quantity'] ?? 0,
                            'created_at' => $now,
                            'updated_at' => $now
                        ]
                    );

                    ItemStock::updateOrCreate(
                        ['item_id' => $item->id, 'stock_id' => $stock->id],
                        [
                            'stock_quantity' => $storeData['stock_quantity'],
                            'version' => DB::raw('version + 1'),
                            'last_modified' => $now,
                            'is_synced' => true,
                            'operation' => 'update',
                            'created_at' => $now,
                            'updated_at' => $now
                        ]
                    );

                    ItemCost::updateOrCreate(
                        ['item_id' => $item->id, 'store_id' => $storeId],
                        [
                            'unit_id' => $request->buying_unit_id,
                            'amount' => $storeData['purchase_rate'],
                            'created_at' => $now,
                            'updated_at' => $now
                        ]
                    );

                    ItemPrice::updateOrCreate(
                        ['item_id' => $item->id, 'store_id' => $storeId],
                        [
                            'unit_id' => $request->selling_unit_id,
                            'amount' => $storeData['selling_price'],
                            'created_at' => $now,
                            'updated_at' => $now
                        ]
                    );

                    if (!empty($storeData['tax_id'])) {
                        ItemTax::updateOrCreate(
                            ['item_id' => $item->id, 'store_id' => $storeId],
                            [
                                'tax_id' => $storeData['tax_id'],
                                'created_at' => $now,
                                'updated_at' => $now
                            ]
                        );
                    } else {
                        ItemTax::where('item_id', $item->id)->where('store_id', $storeId)->delete();
                    }
                }

                $item->load([
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

                Log::info('Item updated successfully', [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'request' => $request->all()
                ]);

                return response()->json([
                    'data' => $item,
                    'message' => 'Item updated successfully'
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('Error updating item: ' . $e->getMessage(), [
                'item_id' => $id,
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to update item: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkName(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for name check', [
                    'errors' => $validator->errors()->toArray(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $item = Item::where('name', $request->name)->whereNull('deleted_at')->first();

            Log::info('Name check performed', [
                'name' => $request->name,
                'exists' => !!$item,
                'item_id' => $item ? $item->id : null
            ]);

            return response()->json([
                'exists' => !!$item,
                'id' => $item ? $item->id : null,
                'message' => 'Name check completed'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error checking item name: ' . $e->getMessage(), [
                'name' => $request->name,
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to check name: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $item = Item::whereNull('deleted_at')->find($id);
            if (!$item) {
                return response()->json([
                    'message' => 'Item not found'
                ], 404);
            }

            return DB::transaction(function () use ($item) {
                $now = Carbon::now();
                $item->update([
                    'deleted_at' => $now,
                    'last_modified' => $now,
                    'is_synced' => true,
                    'operation' => 'delete'
                ]);

                Log::info('Item soft-deleted successfully', [
                    'item_id' => $item->id,
                    'name' => $item->name
                ]);

                return response()->json([
                    'message' => 'Item deleted successfully'
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('Error deleting item: ' . $e->getMessage(), [
                'item_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'Failed to delete item: ' . $e->getMessage()
            ], 500);
        }
    }
}