<?php

namespace App\Http\Controllers;

use App\Enums\TeamPermission;
use App\Models\BillingInvoice;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InvoiceViewController extends Controller
{
    public function show(Request $request, Team $current_team, BillingInvoice $invoice): Response
    {
        $user = Auth::user();

        abort_if(! $user || ! $user->belongsToTeam($current_team), 403);
        abort_if(! $user->hasTeamPermission($current_team, TeamPermission::ViewBilling), 403);
        abort_if($invoice->team_id !== $current_team->id, 404);

        $invoice->load('items.llmModel');

        return response()->view('invoices.show', [
            'invoice' => $invoice,
            'team' => $current_team,
        ]);
    }
}
