<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Produk;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Membuat notifikasi untuk semua admin
     */
    public function createAdminNotification(string $title, string $description, string $type, ?string $link = null, array $data = [])
    {
        try {
            $admins = User::role('admin')->get();

            foreach ($admins as $admin) {
                $this->createNotification($admin->id, $title, $description, $type, $link, $data);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error creating admin notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Membuat notifikasi untuk user tertentu
     * Mencegah duplikasi notifikasi dengan tipe dan konten yang sama dalam waktu 1 jam
     */
    public function createNotification(int $userId, string $title, string $description, string $type, ?string $link = null, array $data = [])
    {
        try {
            // Cek apakah notifikasi serupa sudah ada dalam 1 jam terakhir
            $existingNotification = Notification::where('user_id', $userId)
                ->where('type', $type)
                ->where('title', $title)
                ->where('created_at', '>', now()->subHour())
                ->first();

            if ($existingNotification) {
                // Update waktu notifikasi yang ada
                $existingNotification->update([
                    'unread' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                return $existingNotification;
            }

            // Buat notifikasi baru
            return Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'link' => $link,
                'data' => $data,
                'unread' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating notification: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Memeriksa produk yang stoknya menipis atau tidak valid
     */
    public function checkLowStock()
    {
        try {
            // Dapatkan threshold dari settings
            $lowStockThreshold = (int) Setting::getValue('low_stock_threshold', 10);
            Log::info('Low stock threshold: ' . $lowStockThreshold);
            
            // Dapatkan semua produk yang aktif
            $produks = Produk::where('status', Produk::STATUS_ACTIVE)->get();
            Log::info('Total active products: ' . $produks->count());
            
            // Filter produk yang stoknya menipis atau tidak valid
            $lowStockProduks = $produks->filter(function($item) use ($lowStockThreshold) {
                $hasInvalidStock = $item->has_invalid_stock;
                $isLowStock = $item->available_stock > 0 && $item->available_stock <= $lowStockThreshold;
                
                Log::info(sprintf(
                    'Product ID: %d, Name: %s, Available Stock: %d, Is Low Stock: %s, Has Invalid Stock: %s',
                    $item->id,
                    $item->nama,
                    $item->available_stock,
                    $isLowStock ? 'Yes' : 'No',
                    $hasInvalidStock ? 'Yes' : 'No'
                ));
                
                return $isLowStock || $hasInvalidStock;
            });

            Log::info('Low stock products found: ' . $lowStockProduks->count());

            if ($lowStockProduks->isNotEmpty()) {
                foreach ($lowStockProduks as $item) {
                    if ($item->has_invalid_stock) {
                        $title = 'Stok Produk Tidak Valid';
                        $description = "Stok {$item->nama} tidak valid (Stok: {$item->available_stock}). Jumlah penjualan melebihi pembelian!";
                        $type = 'invalid_stock';
                    } else {
                        $title = 'Stok Produk Menipis';
                        $description = "Stok {$item->nama} tersisa {$item->available_stock} (minimum: {$lowStockThreshold})";
                        $type = 'low_stock';
                    }
                    
                    $link = route('produk.edit', $item->id);
                    Log::info('Creating notification for product: ' . $item->nama);
                    
                    $result = $this->createAdminNotification(
                        $title, 
                        $description, 
                        $type, 
                        $link, [
                            'product_id' => $item->id,
                            'current_stock' => $item->available_stock,
                            'min_stock' => $lowStockThreshold,
                            'has_invalid_stock' => $item->has_invalid_stock
                        ]
                    );
                    
                    Log::info('Notification created: ' . ($result ? 'Yes' : 'No'));
                }
            }

            return $lowStockProduks->count();
        } catch (\Exception $e) {
            Log::error('Error checking low stock: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return 0;
        }
    }

    /**
     * Memeriksa produk yang habis stok
     */
    public function checkOutOfStock()
    {
        try {
            // Dapatkan semua produk yang aktif
            $produks = Produk::where('status', Produk::STATUS_ACTIVE)->get();
            
            // Filter produk yang stoknya habis menggunakan accessor
            $outOfStockProduks = $produks->filter(function($item) {
                // Hanya tampilkan notifikasi untuk stok yang tidak negatif
                return $item->is_out_of_stock && !$item->has_invalid_stock;
            });

            Log::info('Out of stock products found: ' . $outOfStockProduks->count());

            if ($outOfStockProduks->isNotEmpty()) {
                foreach ($outOfStockProduks as $item) {
                    $title = 'Stok Produk Habis';
                    $description = "Stok {$item->nama} sudah habis. Segera lakukan restok.";
                    $link = route('produk.edit', $item->id);
                    
                    $this->createAdminNotification($title, $description, 'out_of_stock', $link, [
                        'product_id' => $item->id,
                        'current_stock' => $item->available_stock,
                        'has_invalid_stock' => false
                    ]);
                }
            }

            return $outOfStockProduks->count();
        } catch (\Exception $e) {
            Log::error('Error checking out of stock: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return 0;
        }
    }

    /**
     * Memeriksa produk yang akan segera kadaluarsa
     */
    public function checkExpiringSoon()
    {
        try {
            $daysBeforeExpiry = (int) Setting::getValue('days_before_expiry_alert', 30);
            $dateThreshold = now()->addDays($daysBeforeExpiry);
            
            // Dapatkan semua produk yang aktif
            $produks = Produk::where('status', Produk::STATUS_ACTIVE)->get();
            
            // Filter produk yang akan segera kadaluarsa
            $expiringSoonProduks = $produks->filter(function($item) use ($dateThreshold) {
                return $item->earliest_expiry && 
                       $item->earliest_expiry <= $dateThreshold &&
                       $item->earliest_expiry >= now() &&
                       $item->available_stock > 0;
            });

            Log::info('Expiring soon products found: ' . $expiringSoonProduks->count());

            if ($expiringSoonProduks->isNotEmpty()) {
                foreach ($expiringSoonProduks as $item) {
                    $title = 'Produk Akan Segera Kadaluarsa';
                    $expiryDate = $item->earliest_expiry->format('d/m/Y');
                    $description = "Produk {$item->nama} akan kadaluarsa pada {$expiryDate}.";
                    $link = route('produk.edit', $item->id);
                    
                    $this->createAdminNotification($title, $description, 'expiring_soon', $link, [
                        'product_id' => $item->id,
                        'expiry_date' => $item->earliest_expiry->toDateString(),
                        'current_stock' => $item->available_stock
                    ]);
                }
            }

            return $expiringSoonProduks->count();
        } catch (\Exception $e) {
            Log::error('Error checking expiring soon: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return 0;
        }
    }

    /**
     * Memeriksa produk yang sudah kadaluarsa
     */
    public function checkExpiredProducts()
    {
        try {
            // Dapatkan semua produk yang aktif
            $produks = Produk::where('status', Produk::STATUS_ACTIVE)->get();
            
            // Filter produk yang memiliki stok kadaluarsa
            $expiredProduks = $produks->filter(function($item) {
                return $item->has_expired_items && $item->available_stock > 0;
            });

            Log::info('Expired products found: ' . $expiredProduks->count());

            if ($expiredProduks->isNotEmpty()) {
                foreach ($expiredProduks as $item) {
                    $title = 'Produk Kadaluarsa';
                    $description = "Produk {$item->nama} memiliki stok yang sudah kadaluarsa.";
                    $link = route('produk.edit', $item->id);
                    
                    $this->createAdminNotification($title, $description, 'expired', $link, [
                        'product_id' => $item->id,
                        'current_stock' => $item->available_stock
                    ]);
                }
            }

            return $expiredProduks->count();
        } catch (\Exception $e) {
            Log::error('Error checking expired products: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return 0;
        }
    }

    /**
     * Memeriksa faktur yang mendekati tenggat pembayaran
     */
    public function checkPaymentDue()
    {
        try {
            $threshold = Carbon::now()->addDays(7); // Faktur yang jatuh tempo dalam 7 hari
            
            $purchases = Purchase::whereNull('tanggal_pembayaran') // Belum dibayar
                ->whereNotNull('jatuh_tempo')
                ->where('jatuh_tempo', '<=', $threshold)
                ->where('jatuh_tempo', '>=', Carbon::now())
                ->get();

            Log::info('Checking payment due invoices. Found: ' . $purchases->count());

            if ($purchases->isNotEmpty()) {
                foreach ($purchases as $purchase) {
                    $daysLeft = Carbon::now()->diffInDays($purchase->jatuh_tempo, false);
                    
                    // Bulatkan ke hari terdekat dan pastikan minimal 1 hari
                    $daysLeft = max(1, round($daysLeft));
                    
                    $title = 'Faktur #' . $purchase->no_faktur . ' Akan Jatuh Tempo';
                    $description = "Faktur #{$purchase->no_faktur} akan jatuh tempo dalam {$daysLeft} hari lagi.";
                    $link = route('purchases.show', $purchase->id);
                    
                    $this->createAdminNotification($title, $description, 'payment_due', $link, [
                        'purchase_id' => $purchase->id,
                        'invoice_number' => $purchase->no_faktur,
                        'due_date' => $purchase->jatuh_tempo,
                        'days_left' => $daysLeft,
                        'formatted_due_date' => $purchase->jatuh_tempo->format('d/m/Y')
                    ]);
                }
            }

            return $purchases->count();
        } catch (\Exception $e) {
            Log::error('Error checking payment due: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return 0;
        }
    }

    /**
     * Memeriksa faktur yang telah melewati tenggat pembayaran
     */
    public function checkOverduePayment()
    {
        try {
            $purchases = Purchase::whereNull('tanggal_pembayaran') // Belum dibayar
                ->whereNotNull('jatuh_tempo')
                ->where('jatuh_tempo', '<', Carbon::now())
                ->get();

            Log::info('Checking overdue payment invoices. Found: ' . $purchases->count());

            if ($purchases->isNotEmpty()) {
                foreach ($purchases as $purchase) {
                    $daysOverdue = Carbon::now()->diffInDays($purchase->jatuh_tempo);
                    
                    // Bulatkan ke hari terdekat dan pastikan minimal 1 hari
                    $daysOverdue = max(1, round($daysOverdue));
                    
                    $title = 'Faktur #' . $purchase->no_faktur . ' Melewati Jatuh Tempo';
                    $description = "Faktur #{$purchase->no_faktur} telah melewati jatuh tempo {$daysOverdue} hari yang lalu.";
                    $link = route('purchases.show', $purchase->id);
                    
                    $this->createAdminNotification($title, $description, 'payment_overdue', $link, [
                        'purchase_id' => $purchase->id,
                        'invoice_number' => $purchase->no_faktur,
                        'due_date' => $purchase->jatuh_tempo,
                        'days_overdue' => $daysOverdue,
                        'formatted_due_date' => $purchase->jatuh_tempo->format('d/m/Y')
                    ]);
                }
            }

            return $purchases->count();
        } catch (\Exception $e) {
            Log::error('Error checking overdue payment: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return 0;
        }
    }
    
    /**
     * Run all notification checks
     *
     * @return array
     */
    public function runAllChecks()
    {
        try {
            Log::info("Running all notification checks");
            
            // Check for invalid stock first (highest priority)
            $invalidStockCount = $this->checkInvalidStock();
            
            $stats = [
                "invalid_stock" => $invalidStockCount,
                "low_stock" => $this->checkLowStock(),
                "out_of_stock" => $this->checkOutOfStock(),
                "expiring_soon" => $this->checkExpiringSoon(),
                "expired" => $this->checkExpiredProducts(),
                "payment_due_soon" => $this->checkPaymentDue(),
                "payment_overdue" => $this->checkOverduePayment()
            ];
            
            Log::info("Notification check results: " . json_encode($stats));
            return $stats;
        } catch (\Exception $e) {
            Log::error("Error running all checks: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [];
        }
    }
    
    /**
     * Check for products with invalid stock (sold more than purchased)
     */
    public function checkInvalidStock()
    {
        try {
            $produks = Produk::where('status', Produk::STATUS_ACTIVE)
                ->withCount(['purchaseDetails as total_purchases' => function($query) {
                    $query->select(DB::raw('COALESCE(SUM(jumlah), 0)'));
                }])
                ->withCount(['saleItems as total_sales' => function($query) {
                    $query->select(DB::raw('COALESCE(SUM(quantity), 0)'));
                }])
                ->get()
                ->filter(function($item) {
                    return $item->has_invalid_stock;
                });
            
            Log::info('Invalid stock products found: ' . $produks->count());
            
            if ($produks->isNotEmpty()) {
                foreach ($produks as $item) {
                    $totalPurchased = $item->purchaseDetails()->sum('jumlah');
                    $totalSold = $item->saleItems()->sum('quantity');
                    $invalidQty = $totalSold - $totalPurchased;
                    
                    $title = 'Stok Produk Tidak Valid';
                    $description = "Produk {$item->nama} memiliki stok tidak valid! " . 
                                 "Total Pembelian: {$totalPurchased}, " .
                                 "Total Penjualan: {$totalSold}, " .
                                 "Kelebihan: {$invalidQty} unit";
                    
                    $link = route('produk.edit', $item->id);
                    
                    $this->createAdminNotification(
                        $title, 
                        $description, 
                        'invalid_stock', 
                        $link, 
                        [
                            'product_id' => $item->id,
                            'product_name' => $item->nama,
                            'total_purchased' => $totalPurchased,
                            'total_sold' => $totalSold,
                            'invalid_quantity' => $invalidQty,
                            'last_updated' => now()->toDateTimeString()
                        ]
                    );
                    
                    Log::warning("Invalid stock detected", [
                        'product_id' => $item->id,
                        'product_name' => $item->nama,
                        'total_purchased' => $totalPurchased,
                        'total_sold' => $totalSold,
                        'invalid_quantity' => $invalidQty
                    ]);
                }
            }
            
            return $produks->count();
        } catch (\Exception $e) {
            Log::error('Error checking invalid stock: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return 0;
        }
    }
}

