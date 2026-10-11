<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan; // Untuk membersihkan cache config
use Illuminate\Support\Facades\File; // Untuk menulis ke file .env (hati-hati!) - Will remove .env update for now
use Inertia\Inertia;
use App\Models\Setting; // Uncommented model
use Illuminate\Support\Facades\Config; // Untuk akses config
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Produk;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\Category;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'app_name' => Setting::getValue('app_name', Config::get('app.name', 'Pharmasys')),
            'low_stock_threshold' => (int) Setting::getValue('low_stock_threshold', 10),
            'default_profit_margin' => (float) Setting::getValue('default_profit_margin', 20),
            // Add other settings as needed
        ];

        // Get data counts for reset display
        $dataCounts = [
            'produk' => Produk::count(),
            'purchase' => Purchase::count(),
            'purchase_detail' => PurchaseDetail::count(),
            'sale' => Sale::count(),
            'sale_item' => SaleItem::count(),
            'supplier' => Supplier::count(),
            'category' => Category::count(),
        ];

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
            'dataCounts' => $dataCounts
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:50',
            'low_stock_threshold' => 'required|integer|min:0',
            'default_profit_margin' => 'required|numeric|min:0',
        ]);

        try {
            Setting::updateOrCreate(['key' => 'app_name'], ['value' => $validated['app_name']]);
            Setting::updateOrCreate(['key' => 'low_stock_threshold'], ['value' => $validated['low_stock_threshold']]);
            Setting::updateOrCreate(['key' => 'default_profit_margin'], ['value' => $validated['default_profit_margin']]);

            // Clear config cache if app_name changed, as it might be used by config('app.name')
            // Note: Directly modifying .env is generally discouraged in production controllers.
            // If .env modification is truly needed, it should be handled with extreme care
            // and potentially restricted to specific CLI commands or admin actions with warnings.
            // For now, we only update the DB setting. The application should preferably read from DB.
            if ($request->has('app_name') && Config::get('app.name') !== $validated['app_name']) {
                 // This updates the live config, but not .env.
                 Config::set('app.name', $validated['app_name']);
                 // Consider if .env update is critical or if reading from DB/config cache is sufficient.
                 // Artisan::call('config:cache'); // Re-cache config
            }

            return redirect()->route('settings.index')->with('success', 'Pengaturan berhasil diperbarui.');

        } catch (\Exception $e) {
            Log::error('Error updating settings: ' . $e->getMessage());
            return redirect()->route('settings.index')->with('error', 'Gagal memperbarui pengaturan: ' . $e->getMessage());
        }
    }

    /**
     * Reset data berdasarkan pilihan pengguna
     */
    public function resetData(Request $request)
    {
        $validated = $request->validate([
            'reset_options' => 'required|array|min:1',
            'reset_options.*' => 'required|string|in:products,purchases,purchase_details,sales,sale_items,suppliers,categories,all',
        ]);

        $resetOptions = $validated['reset_options'];
        $results = [];

        DB::beginTransaction();
        
        try {
            // Reset Products
            if (in_array('products', $resetOptions) || in_array('all', $resetOptions)) {
                $count = Produk::count();
                Produk::truncate();
                $results['products'] = "{$count} produk dihapus";
            }

            // Reset Purchase Details (harus sebelum Purchase karena foreign key)
            if (in_array('purchase_details', $resetOptions) || in_array('all', $resetOptions)) {
                $count = PurchaseDetail::count();
                PurchaseDetail::truncate();
                $results['purchase_details'] = "{$count} detail pembelian dihapus";
            }

            // Reset Purchases
            if (in_array('purchases', $resetOptions) || in_array('all', $resetOptions)) {
                $count = Purchase::count();
                Purchase::truncate();
                $results['purchases'] = "{$count} pembelian dihapus";
            }

            // Reset Sale Items (harus sebelum Sales karena foreign key)
            if (in_array('sale_items', $resetOptions) || in_array('all', $resetOptions)) {
                $count = SaleItem::count();
                SaleItem::truncate();
                $results['sale_items'] = "{$count} item penjualan dihapus";
            }

            // Reset Sales
            if (in_array('sales', $resetOptions) || in_array('all', $resetOptions)) {
                $count = Sale::count();
                Sale::truncate();
                $results['sales'] = "{$count} penjualan dihapus";
            }

            // Reset Suppliers
            if (in_array('suppliers', $resetOptions) || in_array('all', $resetOptions)) {
                $count = Supplier::count();
                Supplier::truncate();
                $results['suppliers'] = "{$count} supplier dihapus";
            }

            // Reset Categories
            if (in_array('categories', $resetOptions) || in_array('all', $resetOptions)) {
                $count = Category::count();
                Category::truncate();
                $results['categories'] = "{$count} kategori dihapus";
            }

            DB::commit();

            $message = implode(', ', array_values($results));
            Log::info('Data reset successful: ' . $message);

            return redirect()->route('settings.index')->with('success', 'Data berhasil direset: ' . $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error resetting data: ' . $e->getMessage());
            return redirect()->route('settings.index')->with('error', 'Gagal mereset data: ' . $e->getMessage());
        }
    }
}
