<?php

namespace App\Jobs;

use App\Mail\TenantUserInvitationMail;
use App\Models\TenantUserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTenantUserInvitationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invitationId,
        public string $token
    ) {}

    public function handle(): void
    {
        $invitation = TenantUserInvitation::query()->find($this->invitationId);
        if (!$invitation) {
            return;
        }

        if ($invitation->accepted_at !== null) {
            return;
        }

        Mail::to($invitation->email)->send(new TenantUserInvitationMail($invitation, $this->token));
    }
}
