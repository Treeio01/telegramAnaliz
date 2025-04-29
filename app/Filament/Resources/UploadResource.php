<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UploadResource\Pages;
use App\Models\Upload;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;

class UploadResource extends Resource
{
    protected static ?string $model = Upload::class;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';
    protected static ?string $navigationLabel = 'Загрузка архивов';
    protected static ?string $navigationGroup = 'Управление';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file')
                    ->label('Архив (.zip)')
                    ->acceptedFileTypes(['application/zip'])
                    ->required()
                    ->disk('local')
                    ->directory('uploads')
                    ->preserveFilenames()
                    ->visibility('private')
                    ->mutateDehydratedStateUsing(fn($state) => $state ? 'uploads/' . $state : null), // <-- добавили это
                Forms\Components\Select::make('type')
                    ->label('Тип архива')
                    ->options([
                        'valid' => 'Живые аккаунты',
                        'dead' => 'Мертвые аккаунты',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('Тип'),
                Tables\Columns\TextColumn::make('created_at')->label('Дата загрузки')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUploads::route('/'),
            'create' => Pages\CreateUpload::route('/create'),
        ];
    }
}
