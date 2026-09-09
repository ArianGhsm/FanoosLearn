# FANOOS UI V2 — Bot Screen Map

| screen | entry | backend source | actions | back target | pagination | Telegram rendering | Bale rendering | permission |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Home | `/start`, `/home`, home callback | workspaces + bounded schedule + optional announcement | courses, schedule, notifications, more, today, web | — | no | Rich semantic | structured text | linked user/workspace reads |
| More | Home | workspace + optional deployment overview | grades, resources, assessments, purchase, workspace, account, management | Home | no | Rich semantic | structured text | normal reads; management separately gated |
| Courses | Home | schedule + grades + resources authorized projections | course detail | Home | bounded catalog | Rich list | text list | whichever course source reads are authorized |
| Course detail | Courses | rebuilt authorized course index | course schedule/resources/grades/assessments/announcements | Courses | no | Rich semantic | structured text | backend rechecked |
| Course schedule | Course | schedule | — | Course | bounded 31-day projection | sections | text sections | `academic.schedule.read` |
| Course resources | Course | resources | resource detail | Course | bounded scan | list/actions | list/actions | `content.catalog.read` |
| Course grades | Course | grades | — | Course | bounded scan | list | list | `grade.self.read` |
| Course assessments | Course | no safe projection yet | website | Course | no | warning/gap | warning/gap | none invented |
| Course announcements | Course | announcement projection lacks course ID | website | Course | no | warning/gap | warning/gap | none invented |
| Schedule hub | Home | workspace | today, tomorrow, week | Home | no | Rich semantic | structured text | workspace |
| Today / Tomorrow | Schedule/Home quick action | schedule | — | Schedule | backend bounded | sections | text sections | `academic.schedule.read` |
| 7 days | Schedule | schedule | prev/next | Schedule | opaque backend cursor behind local route ref | Rich sections | text sections | `academic.schedule.read` |
| Notifications | Home | no personal history projection | announcements, website | Home | no | semantic distinction | semantic distinction | no local inbox authority |
| Announcements | Notifications | `/announcements/list` | prev/next | Notifications | yes | sections | text sections | `announcement.read` |
| Grades | More | `/academics/grades` | prev/next | More | yes | list | list | `grade.self.read` |
| Resources | More | `/content/resources/list` | detail, prev/next | More | yes | list | list | `content.catalog.read` |
| Resource detail | Resources | re-fetched resource catalog | secure receive | Resources | no | facts + CTA | facts + CTA | reauthorized resource view |
| Protected preparing | Resource | issue/consume + protected-media job | refresh | Home | job correlation only | protected-safe | fail closed if protection unsupported | canonical delivery authorization |
| Protected ready | Resource/job | derivative issue/redeem | provider delivery | — | no | `protect_content` | only if provider protection contract permits | canonical delivery authorization |
| Assessments | More | no bot-safe projection | website | More | no | warning/gap | warning/gap | none invented |
| Purchase & access | More | no catalog projection; legacy order APIs retained | website | More | no | semantic CTA | semantic CTA | canonical commerce for legacy order calls |
| Account | More | workspaces/link state | website, workspace, unlink confirm | More | no | facts | facts | linked user |
| Unlink confirm | Account | no read | confirm/cancel | Account | no | warning | warning | unlink rechecked by backend |
| Workspace | More/Account | workspace list/select | switch | More | no | selected list | selected list | canonical membership |
| Management | More | deployment overview | update, last status | More | no | Telegram only | unavailable | private Telegram + `deployment.manage` |
| Update confirm/status | Management | deployment overview/request/status | confirm/cancel/refresh | Management | durable deployment state | Rich semantic | unavailable | Telegram private + backend permission |
| Error/expired | any | safe localized code | Home/context | context/Home | no | severity | severity | no raw provider detail |
