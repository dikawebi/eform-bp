<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveItSpecificationRequest;
use App\Models\ItRequest;
use App\Services\It\SaveItRequestSpecification;
use Illuminate\Http\RedirectResponse;

class ItRequestSpecificationController extends Controller
{
    public function store(SaveItSpecificationRequest $request, ItRequest $it_request): RedirectResponse
    {
        SaveItRequestSpecification::save($it_request, $request->user(), $request->validated());

        return back()->with('success', 'Spesifikasi IT berhasil disimpan.');
    }
}
