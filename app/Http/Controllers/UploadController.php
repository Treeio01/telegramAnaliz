<?php

namespace App\Http\Controllers;

use App\Actions\UploadZipAction;
use App\Models\Upload;
use App\Models\Account;
use App\Models\Vendor;
use Illuminate\Http\Request;
use App\Models\GeoPrice;

class UploadController extends Controller
{
    public function form()
    {
        return view('upload.form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|file|mimes:zip',
            'type' => 'required|in:valid,dead',
        ]);

        $upload = UploadZipAction::handle($request);

        $geos = session()->get("geo_list_for_upload_{$upload->id}", []);

        return redirect()
            ->route('upload.prices', ['upload' => $upload->id])
            ->with('success', 'Архив загружен. Укажите цену по GEO.');
    }

    public function geoPriceForm(Request $request, Upload $upload)
    {
        $geos = session()->get("geo_list_for_upload_{$upload->id}", []);

        if (empty($geos)) {
            return redirect()->route('upload.form')->with('error', 'Не найдены GEO без цены. Проверьте ZIP.');
        }

        $geoPrices = GeoPrice::whereIn('geo', $geos)->pluck('price', 'geo')->toArray();

        return view('upload.geo_prices', [
            'upload' => $upload,
            'geos' => $geos,
            'geoPrices' => $geoPrices,
        ]);
    }


    public function applyGeoPrices(Request $request, Upload $upload)
    {
        $geoPrices = $request->input('geo_prices', []);
        $accounts = session("upload_data_{$upload->id}", []);



        foreach ($accounts as $data) {
            $geo = $data['geo'] ?? null;
            $price = $data['price'] ?? ($geoPrices[$geo] ?? 0);

            foreach ($geoPrices as $geo => $price) {
                GeoPrice::updateOrCreate(
                    ['geo' => $geo],
                    ['price' => $price]
                );
            }

            $vendor = Vendor::firstOrCreate(['name' => $data['role'] ?? 'unknown']);

            Account::create([
                'upload_id' => $upload->id,
                'vendor_id' => $vendor->id,
                'geo' => $geo,
                'price' => $price,
                'phone' => $data['phone'],
                'spamblock' => $data['spamblock'] ?? null,
                'session_created_at' => $data['session_created_date'] ?? null,
                'last_connect_at' => $data['last_connect_date'] ?? null,
                'stats_invites_count' => $data['stats_invites_count'] ?? 0,
            ]);
        }

        session()->forget("upload_data_{$upload->id}");
        session()->forget("geo_list_for_upload_{$upload->id}");

        return redirect()->route('stats.vendors')->with('success', 'Аккаунты успешно импортированы!');
    }
}
