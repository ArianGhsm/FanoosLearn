/*
 * اتاق مطالعه گروهی: GET .../rooms drawn as one card a room, members ranked
 * by today's study; make a room, join one (also straight from an invite
 * link, /app/rooms?join=<code>), leave, and -- for the creator -- remove a
 * member. All text goes in through textContent.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits } from './question-stats.js';
import { answersText, codeFrom, inviteLink, minutesText } from './rooms-rules.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}/rooms`;
const list = document.getElementById('rooms');
const errorBox = document.getElementById('rooms-error');
const doneBox = document.getElementById('rooms-done');

const ERRORS = {
    study_room_name_invalid: 'یک نام کوتاه (تا ۶۰ حرف) برای اتاق بنویس.',
    study_room_not_found: 'این لینک دعوت معتبر نیست یا اتاقش بسته شده.',
    study_room_full: 'این اتاق پر است (۱۰ نفر).',
    study_room_limit: 'تو در ۱۰ اتاق هستی؛ برای پیوستن به اتاق تازه از یکی بیرون بیا.',
};

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function say(box, textId, message) {
    errorBox.hidden = true;
    doneBox.hidden = true;
    document.getElementById(textId).textContent = message;
    box.hidden = false;
}

const fail = (error) => say(errorBox, 'rooms-error-text', ERRORS[error?.code] ?? describeError(error));
const done = (message) => say(doneBox, 'rooms-done-text', message);

function button(text, className, onClick) {
    const node = el('button', className, text);
    node.type = 'button';
    node.addEventListener('click', onClick);
    return node;
}

function roomCard(room) {
    const card = el('article', 'f-card r-room');
    const head = el('div', 'r-room__head');
    head.append(el('h2', '', room.name), el('span', 'r-room__total', `امروز روی هم ${minutesText(room.total_minutes)}`));

    const link = inviteLink(window.location.origin, room.invite_code);
    const invite = el('div', 'r-invite');
    const field = el('input', 'f-input r-invite__link');
    field.value = link;
    field.readOnly = true;
    field.dir = 'ltr';
    invite.append(field, button('کپی لینک دعوت', 'f-btn f-btn--ghost', async () => {
        try {
            await navigator.clipboard.writeText(link);
            done('لینک دعوت کپی شد؛ برای دوستانت بفرست.');
        } catch {
            field.select();
        }
    }));

    const members = el('ol', 'r-members');
    room.members.forEach((member, index) => {
        const row = el('li', `r-member${member.me ? ' is-me' : ''}`);
        row.append(
            el('span', 'r-member__place', faDigits(index + 1)),
            el('span', 'r-member__name', member.me ? `${member.name} (تو)` : member.name),
            el('span', 'r-member__minutes', minutesText(member.minutes)),
            el('span', 'r-member__answers', answersText(member)),
            el('span', 'r-member__points', member.points ? `${faDigits(member.points)} امتیاز` : ''),
        );
        if (room.mine && !member.me) {
            row.append(button('بیرون کردن', 'f-btn f-btn--ghost r-member__remove', async () => {
                try {
                    await api.post(`${base}/${encodeURIComponent(room.id)}/members/${encodeURIComponent(member.user_id)}/remove`, {});
                    await load();
                } catch (error) {
                    fail(error);
                }
            }));
        }
        members.append(row);
    });

    const leave = button('ترک اتاق', 'f-btn f-btn--ghost r-room__leave', async () => {
        if (!leave.dataset.armed) {
            leave.dataset.armed = '1';
            leave.textContent = 'مطمئنی؟ دوباره بزن';
            return;
        }
        try {
            await api.post(`${base}/${encodeURIComponent(room.id)}/leave`, {});
            done(`از «${room.name}» بیرون آمدی.`);
            await load();
        } catch (error) {
            fail(error);
        }
    });

    card.append(head, members, invite, leave);
    return card;
}

async function load() {
    try {
        const rooms = await api.get(base);
        list.replaceChildren(...(rooms.length
            ? rooms.map(roomCard)
            : [el('p', 'f-muted', 'هنوز در هیچ اتاقی نیستی. یکی بساز و لینکش را برای دوستانت بفرست.')]));
    } catch (error) {
        fail(error);
    } finally {
        list.setAttribute('aria-busy', 'false');
    }
}

async function join(code) {
    try {
        const room = await api.post(`${base}/join`, { code });
        done(`به «${room.name}» پیوستی.`);
        await load();
    } catch (error) {
        fail(error);
    }
}

document.getElementById('room-create').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    try {
        await api.post(base, { name: form.elements.name.value });
        form.reset();
        done('اتاق ساخته شد. لینک دعوتش را کپی کن و بفرست.');
        await load();
    } catch (error) {
        fail(error);
    }
});

document.getElementById('room-join').addEventListener('submit', (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const code = codeFrom(form.elements.code.value);
    if (code) join(code).then(() => form.reset());
});

if (workspaceId) {
    const params = new URLSearchParams(window.location.search);
    const invited = codeFrom(params.get('join') ?? '');
    if (invited) {
        // The link has done its job; a reload should not try to join again.
        window.history.replaceState(null, '', '/app/rooms');
        join(invited);
    } else {
        load();
    }
}
