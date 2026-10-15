# User ban scopes

User bans restrict access at three levels. The user management panel determines the scope of each ban and unban action.

| Panel | Ban scope | Default authorized roles |
| --- | --- | --- |
| Administration | Entire application | Admin |
| Conference | That conference and every scheduled conference within it | Admin, that conference's Conference Manager |
| Scheduled Conference | That scheduled conference | Admin, that conference's Conference Manager, that scheduled conference's Scheduled Conference Editor |

Custom roles retain their configured ban permissions within their assigned scope. Direct permissions require membership in the current scope and cannot authorize global bans. Users cannot ban themselves or an Admin account. Non-admin staff can only ban users registered in the scope they manage.

## Access and inheritance

A global ban applies everywhere and signs the user out. A conference ban applies to that conference and every event under it. A scheduled conference ban applies only to that event. Scoped bans return HTTP 403 without invalidating the login session. Other conferences and events remain accessible unless another applicable ban exists.

These checks apply to frontend pages, panels, and Livewire requests. Explicit logout remains available. Ban scope is determined by the server, not submitted form fields.

Unban removes only bans issued in the current scope. Removing an event ban does not remove its conference ban or a global ban. An inherited ban must be removed from the panel where its scope can be managed. The user status displays the broadest applicable active ban.

Expiry dates and reasons remain supported. A missing expiry date means the ban is permanent. Expired or revoked bans do not block access or exclude announcement recipients. Background announcement jobs evaluate bans against the announcement's conference and event, independent of request context.

## Storage and migration

The `bans` table has nullable `conference_id` and `scheduled_conference_id` columns:

| conference_id | scheduled_conference_id | Meaning |
| --- | --- | --- |
| null | null | Global |
| Conference ID | null | Conference |
| Conference ID | Event ID belonging to that conference | Scheduled conference |

Run the standard application database migrations before serving the updated application. Existing ban rows receive null scope columns and remain global. Migration rollback revokes scoped bans before removing their scope columns so they cannot become global bans accidentally.

`User::isBanned()` and `User::notBanned()` evaluate global bans. Contextual access uses `isBannedInCurrentContext()`. Jobs use `notBannedInScope(UserBanScope)` with explicit IDs. Authorized user management actions use `banInCurrentContext()` and `unbanInCurrentContext()`.
