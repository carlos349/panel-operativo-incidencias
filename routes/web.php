<?php

use App\Livewire\IncidentBoard;
use Illuminate\Support\Facades\Route;

Route::livewire('/', IncidentBoard::class)->name('incidents.board');
