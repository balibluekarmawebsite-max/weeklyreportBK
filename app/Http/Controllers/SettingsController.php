<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\MarketSegment;
use App\Models\Property;
use App\Models\RateCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'property' => Property::where('is_active', true)->orderBy('id')->first(),
            'channels' => Channel::orderBy('sort_order')->get(),
            'segments' => MarketSegment::orderBy('sort_order')->get(),
            'rateCodes' => RateCode::orderBy('sort_order')->get(),
            'users' => User::with('roles')->orderBy('name')->get(),
            'roles' => Role::all(),
        ]);
    }
}
