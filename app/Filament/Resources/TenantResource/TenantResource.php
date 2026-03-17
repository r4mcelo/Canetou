<?php

namespace App\Filament\Resources\TenantResource;

use App\Filament\Resources\TenantResource\RelationManagers\ApiKeysRelationManager;
use App\Models\Tenant;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $slug = 'tenants';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static \UnitEnum|string|null $navigationGroup = 'Gestão';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificação')->schema([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                Toggle::make('active')
                    ->label('Ativo')
                    ->default(true)
                    ->inline(false),
            ])->columns(2)
                ->columnSpanFull(),

            Section::make('Provedor de Assinatura')->schema([
                Select::make('provider')
                    ->label('Provedor')
                    ->options(['autentique' => 'Autentique'])
                    ->default('autentique')
                    ->required(),

                TextInput::make('provider_api_key')
                    ->label('API Key do Provedor')
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(500),

                Toggle::make('provider_sandbox')
                    ->label('Modo Sandbox')
                    ->helperText('Ativar para testes — documentos não têm validade jurídica.')
                    ->inline(false),
            ])->columns(2)
                ->columnSpanFull(),

            Section::make('Webhook de Retorno')->schema([
                TextInput::make('webhook_url')
                    ->label('URL do Webhook')
                    ->url()
                    ->placeholder('https://meuapp.com/webhooks/hub')
                    ->helperText('O hub enviará eventos normalizados para esta URL.')
                    ->columnSpanFull(),

                TextInput::make('webhook_secret')
                    ->label('Webhook Secret')
                    ->password()
                    ->revealable()
                    ->helperText('Use para validar a assinatura HMAC-SHA256 no header X-Hub-Signature.')
                    ->columnSpanFull(),
            ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('provider')
                    ->label('Provedor')
                    ->badge()
                    ->color('primary'),

                IconColumn::make('provider_sandbox')
                    ->label('Sandbox')
                    ->boolean(),

                IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('webhook_url')
                    ->label('Webhook URL')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ApiKeysRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
