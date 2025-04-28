<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use App\Models\Account;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Contracts\TranslatableContentDriver;

class VendorAccountsTable extends Component implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    public $vendorId;

    public function table(Table $table): Table
    {
        return $table
            ->query(Account::query()->where('vendor_id', $this->vendorId))
            ->columns([
                TextColumn::make('number')->label('Номер'),
                TextColumn::make('geo')->label('Гео'),
                TextColumn::make('spamblock')->label('Спам'),
                TextColumn::make('date')->label('Дата'),
            ]);
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function render()
    {
        return view('livewire.vendor-accounts-table');
    }
}
