<?php

declare(strict_types=1);

use App\Livewire\Notifications\SmsMessages\Index as SmsMessagesIndex;
use App\Livewire\Notifications\SmsTemplates\Index as SmsTemplatesIndex;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->name('notifications.')->group(function (): void {
    Route::get('/sms-templates', SmsTemplatesIndex::class)->name('sms-templates.index');
    Route::get('/sms-messages', SmsMessagesIndex::class)->name('sms-messages.index');
});
