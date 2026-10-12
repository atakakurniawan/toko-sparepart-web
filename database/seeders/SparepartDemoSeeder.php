<?php

namespace Database\Seeders;

use App\Models\JubelioLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantStock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data demo sparepart untuk mengembangkan katalog sebelum ada akses Jubelio klien.
 * ID Jubelio di sini palsu (mulai 900000) supaya tidak bentrok dengan data asli.
 * Jangan dijalankan di production.
 */
class SparepartDemoSeeder extends Seeder
{
    public function run(): void
    {
        $gudang = JubelioLocation::updateOrCreate(
            ['jubelio_location_id' => -1],
            [
                'name' => 'Pusat (demo)',
                'city' => 'Purwokerto',
                'province' => 'Jawa Tengah',
                'is_warehouse' => true,
                'is_active' => true,
                'serves_website' => true,
            ],
        );

        // [nama, merek, berat gram, label varian, [ [nilai varian, harga, stok], ... ]]
        $katalog = [
            ['Kampas Rem Depan Honda Vario 125', 'Honda Genuine Parts', 150, 'Tipe Motor', [
                ['Vario 125 (2015-2018)', 45000, 25],
                ['Vario 125 (2018-2022)', 47000, 12],
            ]],
            ['Kampas Rem Belakang Yamaha NMAX', 'Yamaha Genuine Parts', 200, 'Tipe Motor', [
                ['NMAX 155 Old', 52000, 8],
                ['NMAX 155 New', 55000, 0],
            ]],
            ['Oli Mesin Matic 10W-30 0.8L', 'AHM Oil', 900, null, [
                [null, 48000, 60],
            ]],
            ['Busi Iridium CPR9EAIX-9', 'NGK', 50, null, [
                [null, 95000, 15],
            ]],
            ['V-Belt Honda Beat FI', 'Honda Genuine Parts', 400, null, [
                [null, 115000, 0],
            ]],
            ['Roller Set Matic 13 gram', 'TDR', 120, 'Ukuran', [
                ['15x12 mm', 60000, 20],
                ['16x13 mm', 62000, 18],
                ['18x14 mm', 65000, 5],
            ]],
            ['Lampu LED Depan H4', 'Osram', 250, 'Warna', [
                ['Putih', 175000, 10],
                ['Kuning', 175000, 3],
            ]],
            ['Filter Udara Yamaha Aerox 155', 'Yamaha Genuine Parts', 300, null, [
                [null, 68000, 9],
            ]],
        ];

        $groupId = 900000;
        $itemId = 900000;

        foreach ($katalog as [$nama, $merek, $berat, $labelVarian, $varian]) {
            $groupId++;

            $product = Product::updateOrCreate(
                ['jubelio_item_group_id' => $groupId],
                [
                    'name' => $nama,
                    'slug' => Str::slug($nama),
                    'description' => "{$nama}. Produk demo untuk pengembangan katalog website.",
                    'brand_name' => $merek,
                    'sell_unit' => 'Buah',
                    'package_weight_gram' => $berat,
                    'variation_labels' => $labelVarian
                        ? [['label' => $labelVarian, 'values' => array_column($varian, 0)]]
                        : [],
                    'is_active' => true,
                    'synced_at' => now(),
                ],
            );

            foreach ($varian as $i => [$nilai, $harga, $stok]) {
                $itemId++;

                $variant = ProductVariant::updateOrCreate(
                    ['jubelio_item_id' => $itemId],
                    [
                        'product_id' => $product->id,
                        'sku' => 'DEMO-'.$groupId.'-'.($i + 1),
                        'name' => $nilai ? "{$nama} - {$nilai}" : $nama,
                        'variant_label' => $nilai,
                        'variation_values' => $nilai ? [['label' => $labelVarian, 'value' => $nilai]] : [],
                        'price' => $harga,
                        'is_active' => true,
                        'synced_at' => now(),
                    ],
                );

                VariantStock::updateOrCreate(
                    [
                        'product_variant_id' => $variant->id,
                        'jubelio_location_id' => $gudang->jubelio_location_id,
                    ],
                    [
                        'on_hand' => $stok,
                        'on_order' => 0,
                        'reserved' => 0,
                        'available' => $stok,
                        'synced_at' => now(),
                    ],
                );
            }
        }
    }
}
