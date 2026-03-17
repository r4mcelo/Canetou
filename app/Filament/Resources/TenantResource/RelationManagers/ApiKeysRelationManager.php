<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ApiKeysRelationManager extends RelationManager
{
    protected static string $relationship = 'apiKeys';

    protected static ?string $title = 'Chaves de API';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),

                IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean(),

                TextColumn::make('last_used_at')
                    ->label('Último uso')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca'),

                TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca'),

                TextColumn::make('created_at')
                    ->label('Criada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('generate')
                    ->label('Gerar Nova Chave')
                    ->icon('heroicon-o-key')
                    ->color('primary')
                    ->form([
                        TextInput::make('name')
                            ->label('Nome da chave')
                            ->placeholder('ex: produção, staging')
                            ->required(),
                        DateTimePicker::make('expires_at')
                            ->label('Expira em')
                            ->helperText('Deixe em branco para nunca expirar.')
                            ->nullable(),
                    ])
                    ->action(function (array $data): void {
                        $token = Str::random(64);

                        $this->getOwnerRecord()->apiKeys()->create([
                            'key' => hash('sha256', $token),
                            'name' => $data['name'],
                            'expires_at' => $data['expires_at'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Chave gerada — copie agora!')
                            ->body(new HtmlString(
                                '<p style="font-size:0.8rem;color:#6b7280;margin-bottom:0.5rem">'
                                .'Esta chave <strong>não será exibida novamente</strong>. Copie antes de fechar.</p>'
                                .'<code style="display:block;background:#f3f4f6;padding:0.6rem;border-radius:6px;'
                                .'font-size:0.7rem;word-break:break-all;line-height:1.6;">'
                                .e($token)
                                .'</code>'
                            ))
                            ->persistent()
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revogar')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->active)
                    ->action(fn ($record) => $record->update(['active' => false])),

                DeleteAction::make()
                    ->label('Excluir'),
            ]);
    }
}
