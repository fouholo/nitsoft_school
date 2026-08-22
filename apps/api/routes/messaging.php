<?php

declare(strict_types=1);

use App\Livewire\Messaging\Index as MessagingIndex;
use Illuminate\Support\Facades\Route;

Route::get('/messagerie/{conversation?}', MessagingIndex::class)->name('messaging.index');
