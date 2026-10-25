<?php

declare(strict_types=1);

namespace App\Staff;

use App\Models\StaffMember;
use Illuminate\Contracts\Session\Session;

/**
 * Who is signed in on this till, and when they last touched it. Lives in the
 * session, so restarting the app signs everyone out. Someone removed from the
 * staff list by a sync is signed out at their next tap.
 */
final class TillSession
{
    private const string STAFF_ID = 'till.staff_id';

    private const string LAST_ACTIVE = 'till.last_active_at';

    public function __construct(private readonly Session $session) {}

    public function signIn(StaffMember $staff): void
    {
        $this->session->migrate(true);
        $this->session->put(self::STAFF_ID, $staff->id);
        $this->touch();
    }

    public function signOut(): void
    {
        $this->session->forget([self::STAFF_ID, self::LAST_ACTIVE]);
    }

    /** The signed-in person, or null once signed out, idle too long or no longer on the staff list. */
    public function staff(): ?StaffMember
    {
        $staffId = $this->session->get(self::STAFF_ID);

        if (! is_string($staffId)) {
            return null;
        }

        $lastActive = (int) $this->session->get(self::LAST_ACTIVE, 0);
        $staff = StaffMember::query()->find($staffId);

        if ($staff === null || now()->getTimestamp() - $lastActive > (int) config('terminal.idle_sign_out_seconds')) {
            $this->signOut();

            return null;
        }

        return $staff;
    }

    /** Called on what the person does, never on background polling. */
    public function touch(): void
    {
        $this->session->put(self::LAST_ACTIVE, now()->getTimestamp());
    }

    public function can(string $permission): bool
    {
        return $this->staff()?->can($permission) ?? false;
    }
}
