<?php

namespace App\Http\Controllers;

use App\Models\PrinterSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PrinterSettingController extends Controller
{
    // Display a listing of printer settings with pagination
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10); // Default to 10 items per page
        $printerSettings = PrinterSetting::with('virtualDevice')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'printer_settings' => $printerSettings->items(),
                'pagination' => [
                    'current_page' => $printerSettings->currentPage(),
                    'total_pages' => $printerSettings->lastPage(),
                    'total_items' => $printerSettings->total(),
                    'per_page' => $printerSettings->perPage(),
                ]
            ]
        ], 200);
    }

    // Store a newly created printer setting
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'virtual_device_id' => 'required|exists:virtual_devices,id',
            'printer_name' => 'required|string|max:255',
            'printer_type' => 'required|in:network,usb,cash_drawer',
            'printer_ip' => 'nullable|string|max:45',
            'printer_port' => 'nullable|integer|min:1',
            'paper_size' => 'nullable|in:80mm,58mm',
            'usb_vendor_id' => 'nullable|string|max:50',
            'usb_product_id' => 'nullable|string|max:50',
            'associated_printer' => 'nullable|string|max:255',
            'drawer_code' => 'nullable|string|max:50',
            'character_set' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'virtual_device_id',
            'printer_name',
            'printer_type',
            'printer_ip',
            'printer_port',
            'paper_size',
            'usb_vendor_id',
            'usb_product_id',
            'associated_printer',
            'drawer_code',
            'character_set',
        ]);

        $printerSetting = PrinterSetting::create($data);
        $printerSetting->load('virtualDevice');

        return response()->json([
            'success' => true,
            'data' => ['printer_setting' => $printerSetting]
        ], 201);
    }

    // Display a specific printer setting
    public function show($id)
    {
        $printerSetting = PrinterSetting::with('virtualDevice')->find($id);
        if (!$printerSetting) {
            return response()->json([
                'success' => false,
                'message' => 'Printer setting not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['printer_setting' => $printerSetting]
        ], 200);
    }

    // Update a specific printer setting
    public function update(Request $request, $id)
    {
        $printerSetting = PrinterSetting::find($id);
        if (!$printerSetting) {
            return response()->json([
                'success' => false,
                'message' => 'Printer setting not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'virtual_device_id' => 'required|exists:virtual_devices,id',
            'printer_name' => 'required|string|max:255',
            'printer_type' => 'required|in:network,usb,cash_drawer',
            'printer_ip' => 'nullable|string|max:45',
            'printer_port' => 'nullable|integer|min:1',
            'paper_size' => 'nullable|in:80mm,58mm',
            'usb_vendor_id' => 'nullable|string|max:50',
            'usb_product_id' => 'nullable|string|max:50',
            'associated_printer' => 'nullable|string|max:255',
            'drawer_code' => 'nullable|string|max:50',
            'character_set' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'virtual_device_id',
            'printer_name',
            'printer_type',
            'printer_ip',
            'printer_port',
            'paper_size',
            'usb_vendor_id',
            'usb_product_id',
            'associated_printer',
            'drawer_code',
            'character_set',
        ]);

        $printerSetting->update($data);
        $printerSetting->load('virtualDevice');

        return response()->json([
            'success' => true,
            'data' => ['printer_setting' => $printerSetting]
        ], 200);
    }

    // Delete a specific printer setting
    public function destroy($id)
    {
        $printerSetting = PrinterSetting::find($id);
        if (!$printerSetting) {
            return response()->json([
                'success' => false,
                'message' => 'Printer setting not found'
            ], 404);
        }

        $printerSetting->delete();
        return response()->json([
            'success' => true,
            'message' => 'Printer setting deleted'
        ], 200);
    }
}