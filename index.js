require('dotenv').config();

const line   = require('@line/bot-sdk');
const express = require('express');
const axios   = require('axios');
const mqtt    = require('mqtt');
const userState = {};

// ── Line config ───────────────────────────────────
const lineConfig = {
    channelAccessToken: process.env.LINE_CHANNEL_ACCESS_TOKEN,
    channelSecret:      process.env.LINE_CHANNEL_SECRET,
};

const client = new line.messagingApi.MessagingApiClient({
    channelAccessToken: process.env.LINE_CHANNEL_ACCESS_TOKEN,
});

// ── Express server ────────────────────────────────
const app = express();

// ── Conversation state ────────────────────────────
const LEAVE_STEPS = {
    SELECT_TYPE: 'select_type',
    START_DATE:  'start_date',
    END_DATE:    'end_date',
    SELECT_DURATION: 'select_duration',
    START_TIME:  'start_time',
    END_TIME:    'end_time',
    REASON:      'reason',
    CONFIRM:     'confirm'
};

const LEAVE_TYPES = ['特休假', '病假', '事假', '公假', '補休', '生理假'];

// Line webhook must use raw body for signature verification
app.post('/webhook',
    line.middleware(lineConfig),
    (req, res) => {
        Promise.all(req.body.events.map(handleEvent))
            .then(result => res.json(result))
            .catch(err  => {
                console.error(err);
                res.status(500).end();
            });
    }
);

// Health check
app.get('/', (req, res) => res.send('Line Bot is running'));

// ── Event handler ─────────────────────────────────
async function handleEvent(event) {
    // Handle postback (button taps)
    if (event.type === 'postback') {
        return handlePostback(event);
    }

    if (event.type !== 'message' || event.message.type !== 'text') {
        return null;
    }

    const userId = event.source.userId;
    const text   = event.message.text.trim();

    console.log(`Message from ${userId}: ${text}`);

    // If user is in a leave application flow, handle their input
    if (userState[userId] && text !== '取消') {
        return handleLeaveFlow(event, userId, text);
    }

    // ── Command routing ───────────────────────────
    if (text === '上班' || text === '上班打卡') {
        return handleClockIn(event, userId);
    }

    if (text === '下班' || text === '下班打卡') {
        return handleClockOut(event, userId);
    }

    if (text === '我的假期' || text === '假期餘額') {
        return handleLeaveBalance(event, userId);
    }

    if (text === '說明' || text === 'help' || text === '？') {
        return handleHelp(event);
    }

    if (text === '請假' || text === '申請請假') {
        return handleLeaveStart(event, userId);
    }

    if (text === '我的請假' || text === '請假記錄') {
        return handleMyLeave(event, userId);
    }

    if (text === '取消' || text === 'cancel') {
        return handleCancel(event, userId);
    }

    // Default reply
    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'text',
            text: '您好！請輸入以下指令：\n\n上班 — 上班打卡\n下班 — 下班打卡\n我的假期 — 查看假期餘額\n說明 — 顯示指令列表'
        }]
    });
}

// ── Clock in ──────────────────────────────────────
async function handleClockIn(event, userId) {
    try {
        const res = await axios.post(
            `${process.env.LARAVEL_API}/line/clock-in`,
            { line_user_id: userId }
        );

        const d = res.data;
        let msg = '';

        if (d.success) {
            msg = d.late_minutes > 0
                ? `✅ 上班打卡成功\n⏰ 時間：${d.clock_in}\n⚠ 今日遲到 ${d.late_minutes} 分鐘`
                : `✅ 上班打卡成功\n⏰ 時間：${d.clock_in}\n🟢 準時上班！`;
        } else {
            msg = `❌ ${d.message}`;
        }

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: msg }]
        });

    } catch (err) {
        console.error('Clock in error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 打卡失敗，請稍後再試或直接使用系統網頁打卡。' }]
        });
    }
}

// ── Clock out ─────────────────────────────────────
async function handleClockOut(event, userId) {
    try {
        const res = await axios.post(
            `${process.env.LARAVEL_API}/line/clock-out`,
            { line_user_id: userId }
        );

        const d = res.data;
        let msg = '';

        if (d.success) {
            msg = d.early_leave_minutes > 0
                ? `✅ 下班打卡成功\n⏰ 時間：${d.clock_out}\n📊 實際工時：${d.worked_hours} 小時\n⚠ 早退 ${d.early_leave_minutes} 分鐘`
                : `✅ 下班打卡成功\n⏰ 時間：${d.clock_out}\n📊 實際工時：${d.worked_hours} 小時`;
        } else {
            msg = `❌ ${d.message}`;
        }

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: msg }]
        });

    } catch (err) {
        console.error('Clock out error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 打卡失敗，請稍後再試。' }]
        });
    }
}

// ── Leave balance ─────────────────────────────────
async function handleLeaveBalance(event, userId) {
    try {
        const res = await axios.get(
            `${process.env.LARAVEL_API}/line/balance`,
            { params: { line_user_id: userId } }
        );

        const d = res.data;

        if (!d.success) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text', text: `❌ ${d.message}` }]
            });
        }

        let msg =
            `📊 ${d.name} 的假期餘額\n` +
            `─────────────────\n` +
            `🏖 特休假剩餘：${d.annual_remaining} 天\n` +
            `⏱ 補休餘額：${d.compensatory_hours} 小時\n` +
            `🤒 病假已請：${d.sick_used} / 30 天\n` +
            `📅 事假已請：${d.personal_used} / 14 天`;

        if (d.gender === 'female') {
            msg += `\n🌸 本月生理假：${d.menstrual_used} / 1 天`;
        }

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: msg }]
        });

    } catch (err) {
        console.error('Balance error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 查詢失敗，請稍後再試。' }]
        });
    }
}

// ── Help ──────────────────────────────────────────
async function handleHelp(event) {
    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'text',
            text:
                '差勤管理系統 指令說明\n' +
                '─────────────────\n' +
                '上班　　 → 上班打卡\n' +
                '下班　　 → 下班打卡\n' +
                '我的假期 → 查詢假期餘額\n' +
                '我的請假 → 查看請假記錄\n' +
                '請假　　 → 申請請假\n' +
                '取消　　 → 取消目前操作\n' +
                '說明　　 → 顯示此說明'
        }]
    });
}

// ── Leave application flow ────────────────────────

async function handleLeaveStart(event, userId) {
    // Check if employee exists
    const empRes = await axios.get(
        `${process.env.LARAVEL_API}/line/balance`,
        { params: { line_user_id: userId } }
    ).catch(() => null);

    if (!empRes?.data?.success) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 找不到您的員工帳號，請聯繫人資部綁定 Line 帳號。' }]
        });
    }

    // Get gender to filter leave types
    const gender = empRes.data.gender;

    // Init state
    userState[userId] = { step: LEAVE_STEPS.SELECT_TYPE, gender };

    // Build quick reply items
    const leaveItems = LEAVE_TYPES
        .filter(t => t !== '生理假' || gender === 'female')
        .map(t => ({
            type: 'action',
            action: {
                type: 'postback',
                label: t,
                data: `action=select_leave_type&type=${encodeURIComponent(t)}`,
                displayText: t
            }
        }));

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'text',
            text: '📋 申請請假\n請選擇假別：\n\n（輸入「取消」可隨時取消）',
            quickReply: { items: leaveItems }
        }]
    });
}


async function handleLeaveFlow(event, userId, text) {
    const state = userState[userId];

    if (!state) return null;

    if (state.step === LEAVE_STEPS.START_DATE) {
        return handleLeaveStartDate(event, userId, text);
    }

    if (state.step === LEAVE_STEPS.END_DATE) {
        return handleLeaveEndDate(event, userId, text);
    }

    if (state.step === LEAVE_STEPS.REASON) {
        return handleLeaveReason(event, userId, text);
    }

    return null;
}


async function handleLeaveStartDate(event, userId, text) {
    // Validate date format YYYY-MM-DD
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(text)) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 日期格式不正確，請輸入格式：2026-10-19' }]
        });
    }

    const date    = new Date(text);
    const today   = new Date();
    today.setHours(0, 0, 0, 0);

    if (isNaN(date.getTime())) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 無效的日期，請重新輸入。' }]
        });
    }

    //Past date check
    if (date < today) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{
                type: 'text',
                text: '❌ 開始日期不能早於今天，請重新選擇：',
                quickReply: {
                    items: [{
                        type: 'action',
                        action: {
                            type: 'datetimepicker',
                            label: '📅 重新選擇開始日期',
                            data: 'action=pick_start_date',
                            mode: 'date',
                            initial: today.toISOString().split('T')[0],
                            min: today.toISOString().split('T')[0],
                            max: '2027-12-31'
                        }
                    }]
                }
            }]
        });
    }

    // Same-day after 12pm check
    const now = new Date();
    const isToday = date.toDateString() === now.toDateString();
    if (isToday && now.getHours() >= 12) {
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{
                type: 'text',
                text: '❌ 當日請假須於中午12:00前提出申請，請重新選擇開始日期：',
                quickReply: {
                    items: [{
                        type: 'action',
                        action: {
                            type: 'datetimepicker',
                            label: '📅 重新選擇開始日期',
                            data: 'action=pick_start_date',
                            mode: 'date',
                            initial: tomorrow.toISOString().split('T')[0],
                            min: tomorrow.toISOString().split('T')[0],
                            max: '2027-12-31'
                        }
                    }]
                }
            }]
        });
    }

    //Weekend check
    const dayOfWeek = date.getDay();
    if (dayOfWeek === 0 || dayOfWeek === 6) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{
                type: 'text',
                text: '❌ 開始日期不能選週末，請重新選擇：',
                quickReply: {
                    items: [{
                        type: 'action',
                        action: {
                            type: 'datetimepicker',
                            label: '📅 重新選擇開始日期',
                            data: 'action=pick_start_date',
                            mode: 'date',
                            initial: text,
                            min: userState[userId].start_date,
                            max: '2027-12-31'
                        }
                    }]
                }
            }]
        });
    }

    userState[userId].start_date = text;
    userState[userId].step       = LEAVE_STEPS.END_DATE;

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'text',
            text: `✅ 開始日期：${text}\n\n請選擇結束日期：`,
            quickReply: {
                items: [{
                    type: 'action',
                    action: {
                        type: 'datetimepicker',
                        label: '📅 選擇結束日期',
                        data: 'action=pick_end_date',
                        mode: 'date',
                        initial: text,
                        min: text,
                        max: '2027-12-31'
                    }
                }]
            }
        }]
    });
}


async function handleLeaveEndDate(event, userId, text) {
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(text)) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 日期格式不正確，請重新選擇。' }]
        });
    }

    const endDate   = new Date(text);
    const startDate = new Date(userState[userId].start_date);

    if (isNaN(endDate.getTime())) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 無效的日期，請重新選擇。' }]
        });
    }

    if (endDate < startDate) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{
                type: 'text',
                text: '❌ 結束日期不能早於開始日期，請重新選擇：',
                quickReply: {
                    items: [{
                        type: 'action',
                        action: {
                            type: 'datetimepicker',
                            label: '📅 重新選擇結束日期',
                            data: 'action=pick_end_date',
                            mode: 'date',
                            initial: userState[userId].start_date,
                            min: userState[userId].start_date,
                            max: '2027-12-31'
                        }
                    }]
                }
            }]
        });
    }

    const dayOfWeek = endDate.getDay();
    if (dayOfWeek === 0 || dayOfWeek === 6) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 結束日期不能選週末，請重新選擇：',
                quickReply: {
                    items: [{
                        type: 'action',
                        action: {
                            type: 'datetimepicker',
                            label: '📅 重新選擇結束日期',
                            data: 'action=pick_end_date',
                            mode: 'date',
                            initial: text,
                            min: userState[userId].start_date,
                            max: '2027-12-31'
                        }
                    }]
                }
            }]
        });
    }

    userState[userId].end_date = text;
    
    // If same day → offer hourly option
    if (userState[userId].start_date === text) {
        userState[userId].step = LEAVE_STEPS.SELECT_DURATION;
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{
                type: 'text',
                text: '請選擇請假方式：',
                quickReply: {
                    items: [
                        {
                            type: 'action',
                            action: {
                                type: 'postback',
                                label: '全天請假',
                                data: 'action=select_duration&duration=fullday',
                                displayText: '全天請假'
                            }
                        },
                        {
                            type: 'action',
                            action: {
                                type: 'postback',
                                label: '小時請假',
                                data: 'action=select_duration&duration=hourly',
                                displayText: '小時請假'
                            }
                        }
                    ]
                }
            }]
        });
    }

    //Multiday leave → skip hourly, proceed to reason
    userState[userId].step     = LEAVE_STEPS.REASON;
    userState[userId].start_time = null; 
    userState[userId].end_time   = null;

    // Calculate weekdays
    const days = countWeekdays(startDate, endDate);

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{ type: 'text',
            text: `✅ 結束日期：${text}\n📅 工作天數：${days} 天\n\n請輸入請假原因：` }]
    });
}

async function handleDurationSelected(event, userId, duration) {
    if (!userState[userId]) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '操作已逾時，請重新輸入「請假」。' }]
        });
    }

    if (duration === 'fullday') {
        userState[userId].start_time = null;
        userState[userId].end_time   = null;
        userState[userId].step       = LEAVE_STEPS.REASON;

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '✅ 全天請假\n\n請輸入請假原因：' }]
        });
    }

    if (duration === 'hourly') {
        userState[userId].step = LEAVE_STEPS.START_TIME;

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{
                type: 'flex',
                altText: '請選擇開始時間',
                contents: {
                    type: 'bubble',
                    size: 'kilo',
                    body: {
                        type: 'box',
                        layout: 'vertical',
                        spacing: 'md',
                        paddingAll: '16px',
                        contents: [
                            {
                                type: 'text',
                                text: '請選擇開始時間',
                                weight: 'bold',
                                size: 'md',
                                color: '#1F3864'
                            },
                            {
                                type: 'text',
                                text: '點擊下方按鈕選擇時間',
                                size: 'sm',
                                color: '#888888'
                            },
                            {
                                type: 'button',
                                action: {
                                    type: 'datetimepicker',
                                    label: '選擇開始時間',
                                    data: 'action=select_start_time',
                                    mode: 'time',
                                    initial: '09:00',
                                    min: '09:00',
                                    max: '17:30',
                                },
                                style: 'primary',
                                color: '#1F3864',
                                margin: 'md'
                            }
                        ]
                    }
                }
            }]
        });
    }

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'text',
            text: '請選擇開始時間：',
            quickReply: {
                items: commonTimes.map(t => ({
                    type: 'action',
                    action: {
                        type: 'postback',
                        label: t,
                        data: `action=select_start_time&time=${t}`,
                        displayText: `開始時間：${t}`
                    }
                }))
            }
        }]
    });
}

async function handleStartTimeSelected(event, userId, time) {
    // time comes from datetimepicker postback as HH:mm
    if (!userState[userId]) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '操作已逾時，請重新輸入「請假」。' }]
        });
    }

    userState[userId].start_time = time;
    userState[userId].step       = LEAVE_STEPS.END_TIME;

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'flex',
            altText: '請選擇結束時間',
            contents: {
                type: 'bubble',
                size: 'kilo',
                body: {
                    type: 'box',
                    layout: 'vertical',
                    spacing: 'md',
                    paddingAll: '16px',
                    contents: [
                        {
                            type: 'text',
                            text: `✅ 開始時間：${time}`,
                            size: 'sm',
                            color: '#198754',
                            weight: 'bold'
                        },
                        {
                            type: 'text',
                            text: '請選擇結束時間',
                            weight: 'bold',
                            size: 'md',
                            color: '#1F3864',
                            margin: 'md'
                        },
                        {
                            type: 'button',
                            action: {
                                type: 'datetimepicker',
                                label: '選擇結束時間',
                                data: 'action=select_end_time',
                                mode: 'time',
                                initial: time < '12:00' ? '12:00' : '18:00',
                                min: time,
                                max: '18:00',
                            },
                            style: 'primary',
                            color: '#1F3864',
                            margin: 'md'
                        }
                    ]
                }
            }
        }]
    });
}


async function handleEndTimeSelected(event, userId, time) {
    const state = userState[userId];
    if (!state) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '操作已逾時，請重新輸入「請假」。' }]
        });
    }

    const [sh, sm] = state.start_time.split(':').map(Number);
    const [eh, em] = time.split(':').map(Number);
    let totalMins  = (eh * 60 + em) - (sh * 60 + sm);

    if (totalMins <= 0) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 結束時間必須晚於開始時間，請重新選擇。' }]
        });
    }

    // Subtract lunch 12:00-13:00 overlap
    const oStart = Math.max(sh * 60 + sm, 12 * 60);
    const oEnd   = Math.min(eh * 60 + em, 13 * 60);
    if (oEnd > oStart) totalMins -= (oEnd - oStart);

    const hours = Math.round((totalMins / 60) * 10) / 10;

    userState[userId].end_time = time;
    userState[userId].hours    = hours;
    userState[userId].step     = LEAVE_STEPS.REASON;

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{ type: 'text',
            text: `✅ 時段：${state.start_time} – ${time}\n⏱ 時數：${hours} 小時（已扣除午休）\n\n請輸入請假原因：`
        }]
    });
}

async function handleLeaveReason(event, userId, text) {
    if (text.trim().length < 2) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 請假原因至少需要2個字，請重新輸入。' }]
        });
    }

    userState[userId].reason = text.trim();
    userState[userId].step   = LEAVE_STEPS.CONFIRM;

    const s    = userState[userId];
    const days = s.hours
        ? Math.round((s.hours / 8) * 100) / 100
        : countWeekdays(new Date(s.start_date), new Date(s.end_date));
    
    // Build body rows
    const bodyRows = [
        makeRow('假別', s.leave_type),
        makeRow('日期', s.start_date === s.end_date ? s.start_date : `${s.start_date} ~ ${s.end_date}`),
    ];

    if (s.start_time && s.end_time) {
        bodyRows.push(makeRow('時段', `${s.start_time} – ${s.end_time}`));
        bodyRows.push(makeRow('時數', `${s.hours} 小時`));
    } else {
        bodyRows.push(makeRow('天數', `${days} 天`));
    }

    bodyRows.push(makeRow('原因', s.reason));
        
    // Show confirmation Flex Message
    const confirmFlex = {
        type: 'flex',
        altText: '請確認您的請假申請',
        contents: {
            type: 'bubble',
            size: 'kilo',
            header: {
                type: 'box',
                layout: 'vertical',
                contents: [{
                    type: 'text',
                    text: '📋 請假申請確認',
                    color: '#ffffff',
                    size: 'md',
                    weight: 'bold'
                }],
                backgroundColor: '#1F3864',
                paddingAll: '16px'
            },
            body: {
                type: 'box',
                layout: 'vertical',
                spacing: 'sm',
                paddingAll: '16px',
                contents: [
                    makeRow('假別', s.leave_type),
                    makeRow('開始', s.start_date),
                    makeRow('結束', s.end_date),
                    makeRow('天數', `${days} 天`),
                    makeRow('原因', s.reason),
                    { type: 'separator', margin: 'md' },
                    {
                        type: 'box',
                        layout: 'horizontal',
                        margin: 'md',
                        spacing: 'sm',
                        contents: [
                            {
                                type: 'button',
                                action: {
                                    type: 'postback',
                                    label: '✅ 送出申請',
                                    data: `action=submit_leave`,
                                    displayText: '送出請假申請'
                                },
                                style: 'primary',
                                color: '#198754',
                                height: 'sm'
                            },
                            {
                                type: 'button',
                                action: {
                                    type: 'postback',
                                    label: '❌ 取消',
                                    data: `action=cancel_leave`,
                                    displayText: '取消申請'
                                },
                                style: 'secondary',
                                height: 'sm'
                            }
                        ]
                    }
                ]
            }
        }
    };

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [confirmFlex]
    });
}


async function handleCancel(event, userId) {
    if (userState[userId]) {
        delete userState[userId];
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '已取消操作。' }]
        });
    }
    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{ type: 'text', text: '目前沒有進行中的操作。' }]
    });
}

// ── My leave records ──────────────────────────────
async function handleMyLeave(event, userId) {
    try {
        const res = await axios.get(
            `${process.env.LARAVEL_API}/line/my-leaves`,
            { params: { line_user_id: userId } }
        );

        const d = res.data;

        if (!d.success) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text', text: `❌ ${d.message}` }]
            });
        }

        if (!d.leaves || d.leaves.length === 0) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: `${d.name} 目前沒有請假記錄。` }]
            });
        }

        // Status emoji map
        const statusEmoji = {
            '草稿':  '📝',
            '簽核中': '⏳',
            '已核准': '✅',
            '已拒絕': '❌',
        };

        // Build Flex Message carousel (one bubble per leave)
        const bubbles = d.leaves.map(leave => ({
            type: 'bubble',
            size: 'kilo',
            header: {
                type: 'box',
                layout: 'horizontal',
                contents: [
                    {
                        type: 'text',
                        text: leave.leave_type,
                        color: '#ffffff',
                        size: 'sm',
                        weight: 'bold',
                        flex: 3
                    },
                    {
                        type: 'text',
                        text: `${statusEmoji[leave.status] || '❓'} ${leave.status}`,
                        color: '#ffffff',
                        size: 'sm',
                        align: 'end',
                        flex: 2
                    }
                ],
                backgroundColor: leave.status === '已核准' ? '#198754'
                    : leave.status === '已拒絕'            ? '#dc3545'
                    : leave.status === '簽核中'            ? '#2E74B5'
                    : '#6c757d',
                paddingAll: '12px'
            },
            body: {
                type: 'box',
                layout: 'vertical',
                spacing: 'sm',
                paddingAll: '12px',
                contents: [
                    makeRow('日期',
                        leave.start_date === leave.end_date
                            ? leave.start_date
                            : `${leave.start_date} ~ ${leave.end_date}`
                    ),
                    makeRow('天數', `${leave.days} 天`),
                    ...(leave.admin_note ? [makeRow('備註', leave.admin_note)] : []),
                ]
            }
        }));

        // Use carousel if multiple, single bubble if one
        const contents = bubbles.length === 1
            ? bubbles[0]
            : { type: 'carousel', contents: bubbles };

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [
                {
                    type: 'text',
                    text: `${d.name} 的最近請假記錄（最多5筆）：`
                },
                {
                    type: 'flex',
                    altText: '您的請假記錄',
                    contents
                }
            ]
        });

    } catch (err) {
        console.error('My leave error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 查詢失敗，請稍後再試。' }]
        });
    }
}

// ── Helper: build a row for Flex Message body ─────
function makeRow(label, value) {
    return {
        type: 'box',
        layout: 'horizontal',
        contents: [
            {
                type: 'text',
                text: label,
                size: 'sm',
                color: '#888888',
                flex: 2
            },
            {
                type: 'text',
                text: String(value),
                size: 'sm',
                color: '#333333',
                flex: 4,
                wrap: true
            }
        ]
    };
}


// ── Helper: count weekdays between two dates ──────
function countWeekdays(start, end) {
    let count = 0;
    const cur = new Date(start);
    while (cur <= end) {
        const day = cur.getDay();
        if (day !== 0 && day !== 6) count++;
        cur.setDate(cur.getDate() + 1);
    }
    return count;
}

// ── Postback handler (button taps) ───────────────
async function handlePostback(event) {
    const params   = new URLSearchParams(event.postback.data);
    const action   = params.get('action');
    const leaveId  = params.get('leave_id');
    const empName  = decodeURIComponent(params.get('employee_name') || '');
    const leaveType = decodeURIComponent(params.get('leave_type') || '');
    const userId   = event.source.userId;

    if (action === 'approve') {
        return handleLineApprove(event, leaveId, empName, leaveType);
    }

    if (action === 'reject_prompt') {
        return handleLineRejectPrompt(event, leaveId, empName, leaveType);
    }

    if (action === 'reject_confirm') {
        const reason = decodeURIComponent(params.get('reason') || '');
        return handleLineReject(event, leaveId, empName, leaveType, reason);
    }

    if (action === 'select_leave_type') {
        const type = decodeURIComponent(params.get('type') || '');
        return handleLeaveTypeSelected(event, userId, type);
    }

    if (action === 'select_duration') {
        const duration = params.get('duration');
        return handleDurationSelected(event, userId, duration);
    }

    if (action === 'select_start_time') {
        const time = event.postback.params?.time || params.get('time');
        return handleStartTimeSelected(event, userId, time);
    }

    if (action === 'select_end_time') {
        const time = event.postback.params?.time || params.get('time');
        return handleEndTimeSelected(event, userId, time);
    }

    if (action === 'pick_start_date') {
        const date = event.postback.params.date;
        return handleLeaveStartDate(event, userId, date);
    }

    if (action === 'pick_end_date') {
        const date = event.postback.params.date;
        return handleLeaveEndDate(event, userId, date);
    }

    if (action === 'submit_leave') {
        return handleLeaveSubmit(event, userId);
    }

    if (action === 'cancel_leave') {
        delete userState[userId];
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '已取消請假申請。' }]
        });
    }

    return null;
}

async function handleLeaveTypeSelected(event, userId, type) {
    if (!userState[userId]) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '操作已逾時，請重新輸入「請假」開始申請。' }]
        });
    }

    userState[userId].leave_type = type;
    userState[userId].step       = LEAVE_STEPS.START_DATE;

    const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'text',
            text: `假別：${type}\n\n請選擇開始日期：`,
            quickReply: {
                items: [{
                    type: 'action',
                    action: {
                        type: 'datetimepicker',
                        label: '📅 選擇開始日期',
                        data: `action=pick_start_date`,
                        mode: 'date',
                        initial: today,
                        min: today,
                        max: '2027-12-31'
                    }
                }]
            }
        }]
    });
}


async function handleLeaveSubmit(event, userId) {
    const state = userState[userId];

    if (!state || state.step !== LEAVE_STEPS.CONFIRM) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 操作已逾時，請重新輸入「請假」開始申請。' }]
        });
    }

    try {
        const res = await axios.post(
            `${process.env.LARAVEL_API}/line/leave-submit`,
            {
                line_user_id: userId,
                leave_type:   state.leave_type,
                start_date:   state.start_date,
                end_date:     state.end_date,
                start_time:   state.start_time || null,
                end_time:     state.end_time   || null,
                hours:        state.hours      || null,
                leave_reason: state.reason,
            }
        );

        delete userState[userId];

        if (res.data.success) {
            const timeInfo = state.start_time
                ? `時段：${state.start_time} – ${state.end_time}` 
                : '';

            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: `✅ 請假申請已送出！\n\n假別：${state.leave_type}\n日期：${state.start_date} ~ ${state.end_date}\n${timeInfo}\n\n等待主管審核，審核結果將透過 Line 通知您。` }]
            });
        } else {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text', text: `❌ 申請失敗：${res.data.message}` }]
            });
        }

    } catch (err) {
        console.error('Leave submit error:', err.response?.data || err.message);
        delete userState[userId];
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 申請失敗，請稍後再試或至系統網頁申請。' }]
        });
    }
}

async function handleLineApprove(event, leaveId, empName, leaveType) {
    try {
        const res = await axios.post(
            `${process.env.LARAVEL_API}/line/leave-approve`,
            {
                leave_id:   leaveId,
                manager_line_id: event.source.userId,
            }
        );

        const msg = res.data.success
            ? `✅ 已核准 ${empName} 的${leaveType}申請`
            : `❌ 操作失敗：${res.data.message}`;

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: msg }]
        });

    } catch (err) {
        console.error('Line approve error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 操作失敗，請至系統網頁審核。' }]
        });
    }
}


async function handleLineRejectPrompt(event, leaveId, empName, leaveType) {
    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'text',
            text: `請選擇拒絕 ${empName} 的${leaveType}申請的原因：`,
            quickReply: {
                items: [
                    {
                        type: 'action',
                        action: {
                            type: 'postback',
                            label: '人力不足請改期',
                            data: `action=reject_confirm&leave_id=${leaveId}&employee_name=${encodeURIComponent(empName)}&leave_type=${encodeURIComponent(leaveType)}&reason=${encodeURIComponent('人力不足請改期')}`,
                            displayText: '人力不足請改期'
                        }
                    },
                    {
                        type: 'action',
                        action: {
                            type: 'postback',
                            label: '日期與業務衝突',
                            data: `action=reject_confirm&leave_id=${leaveId}&employee_name=${encodeURIComponent(empName)}&leave_type=${encodeURIComponent(leaveType)}&reason=${encodeURIComponent('日期與業務衝突')}`,
                            displayText: '日期與業務衝突'
                        }
                    },
                    {
                        type: 'action',
                        action: {
                            type: 'postback',
                            label: '假期餘額不足',
                            data: `action=reject_confirm&leave_id=${leaveId}&employee_name=${encodeURIComponent(empName)}&leave_type=${encodeURIComponent(leaveType)}&reason=${encodeURIComponent('假期餘額不足')}`,
                            displayText: '假期餘額不足'
                        }
                    },
                    {
                        type: 'action',
                        action: {
                            type: 'postback',
                            label: '請至系統填寫原因',
                            data: `action=reject_confirm&leave_id=${leaveId}&employee_name=${encodeURIComponent(empName)}&leave_type=${encodeURIComponent(leaveType)}&reason=${encodeURIComponent('主管透過 Line 拒絕，詳情請至系統查看。')}`,
                            displayText: '請至系統填寫原因'
                        }
                    }
                ]
            }
        }]
    });
}


async function handleLineReject(event, leaveId, empName, leaveType, reason) {
    try {
        const res = await axios.post(
            `${process.env.LARAVEL_API}/line/leave-reject`,
            {
                leave_id:        leaveId,
                admin_note:      reason,
                manager_line_id: event.source.userId,
            }
        );

        const msg = res.data.success
            ? `❌ 已拒絕 ${empName} 的${leaveType}申請\n原因：${reason}`
            : `❌ 操作失敗：${res.data.message}`;

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: msg }]
        });

    } catch (err) {
        console.error('Line reject error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 操作失敗，請至系統網頁審核。' }]
        });
    }
}

// ── MQTT subscriber ───────────────────────────────
// Receives events from Laravel and pushes Line notifications
const mqttClient = mqtt.connect(process.env.MQTT_BROKER);

mqttClient.on('connect', () => {
    console.log('Connected to MQTT broker');
    mqttClient.subscribe('leave/approved');
    mqttClient.subscribe('leave/rejected');
    mqttClient.subscribe('leave/submitted');
    mqttClient.subscribe('overtime/confirmed');
});

mqttClient.on('message', async (topic, payload) => {
    try {
        const data = JSON.parse(payload.toString());
        console.log(`MQTT received: ${topic}:`, data);

        if (topic === 'leave/approved') {
            await pushLeaveApprovedNotification(data);
        } else if (topic === 'leave/rejected') {
            await pushLeaveRejectedNotification(data);
        } else if (topic === 'leave/submitted') {
            await pushLeaveSubmittedToManager(data);
        } else if (topic === 'overtime/confirmed') {
            await pushOvertimeConfirmedNotification(data);
        }
    } catch (err) {
        console.error('MQTT message error:', err.message);
    }
});

// ── Push notification helpers ─────────────────────
async function pushLeaveApprovedNotification(data) {
    // Look up employee's Line user ID from Laravel
    try {
        const res = await axios.get(
            `${process.env.LARAVEL_API}/line/user-id`,
            { params: { employee_id: data.employee_id } }
        );

        if (!res.data.line_user_id) return;

        const msg =
            `✅ 您的請假申請已核准\n` +
            `假別：${data.leave_type}\n` +
            `日期：${data.start_date} ~ ${data.end_date}\n` +
            `核准人：${data.approved_by}`;

        await client.pushMessage({
            to: res.data.line_user_id,
            messages: [{ type: 'text', text: msg }]
        });

        console.log(`Pushed leave approved to employee ${data.employee_id}`);
    } catch (err) {
        console.error('Push approved error:', err.message);
    }
}

async function pushLeaveRejectedNotification(data) {
    try {
        const res = await axios.get(
            `${process.env.LARAVEL_API}/line/user-id`,
            { params: { employee_id: data.employee_id } }
        );

        if (!res.data.line_user_id) return;

        const msg =
            `❌ 您的請假申請已被拒絕\n` +
            `假別：${data.leave_type}\n` +
            `原因：${data.admin_note}\n` +
            `拒絕人：${data.rejected_by}`;

        await client.pushMessage({
            to: res.data.line_user_id,
            messages: [{ type: 'text', text: msg }]
        });
    } catch (err) {
        console.error('Push rejected error:', err.message);
    }
}

async function pushOvertimeConfirmedNotification(data) {
    try {
        const res = await axios.get(
            `${process.env.LARAVEL_API}/line/user-id`,
            { params: { employee_id: data.employee_id } }
        );

        if (!res.data.line_user_id) return;

        const msg =
            `⏰ 您的加班記錄已確認\n` +
            `日期：${data.date}\n` +
            `時數：${data.hours} 小時\n` +
            `補休餘額已自動增加`;

        await client.pushMessage({
            to: res.data.line_user_id,
            messages: [{ type: 'text', text: msg }]
        });
    } catch (err) {
        console.error('Push overtime error:', err.message);
    }
}

async function pushLeaveSubmittedToManager(data) {
    try {
        //Find manager's Line user ID based on department
        const res = await axios.get(
            `${process.env.LARAVEL_API}/line/manager`,
            { params: { department: data.department } }
        );

        // If the applicant IS the manager, send to HR instead
        if (!res.data.line_user_id ||
            res.data.line_user_id === data.employee_line_id) {

            // Send to HR
            const hrRes = await axios.get(
                `${process.env.LARAVEL_API}/line/hr`,
                { params: {} }
            );

            if (!hrRes.data.line_user_id) {
                console.log('No HR Line ID found, skipping notification');
                return;
            }

            const msg =
                `📋 新請假申請待審核（主管申請）\n` +
                `─────────────────\n` +
                `申請人：${data.employee_name}（${data.employee_no}）\n` +
                `部門：${data.department}\n` +
                `假別：${data.leave_type}\n` +
                `日期：${data.start_date} ~ ${data.end_date}\n` +
                `天數：${data.days} 天\n\n` +
                `請至系統審核`;

            await client.pushMessage({
                to: hrRes.data.line_user_id,
                messages: [{ type: 'text', text: msg }]
            });

            console.log('Leave submitted by manager — notified HR instead');
            return;
        }

        const flexMessage = {
            type: 'flex',
            altText: `📋 新請假申請：${data.employee_name}`,
            contents: {
                type: 'bubble',
                size: 'kilo',
                header: {
                    type: 'box',
                    layout: 'vertical',
                    contents: [{
                        type: 'text',
                        text: '📋 新請假申請',
                        color: '#ffffff',
                        size: 'md',
                        weight: 'bold'
                    }],
                    backgroundColor: '#1F3864',
                    paddingAll: '16px'
                },
                body: {
                    type: 'box',
                    layout: 'vertical',
                    spacing: 'sm',
                    paddingAll: '16px',
                    contents: [
                        {
                            type: 'box',
                            layout: 'horizontal',
                            contents: [
                                {
                                    type: 'text',
                                    text: '申請人',
                                    size: 'sm',
                                    color: '#888888',
                                    flex: 2
                                },
                                {
                                    type: 'text',
                                    text: `${data.employee_name}（${data.employee_no}）`,
                                    size: 'sm',
                                    color: '#333333',
                                    flex: 4,
                                    weight: 'bold'
                                }
                            ]
                        },
                        {
                            type: 'box',
                            layout: 'horizontal',
                            contents: [
                                {
                                    type: 'text',
                                    text: '假別',
                                    size: 'sm',
                                    color: '#888888',
                                    flex: 2
                                },
                                {
                                    type: 'text',
                                    text: data.leave_type,
                                    size: 'sm',
                                    color: '#333333',
                                    flex: 4
                                }
                            ]
                        },
                        {
                            type: 'box',
                            layout: 'horizontal',
                            contents: [
                                {
                                    type: 'text',
                                    text: '日期',
                                    size: 'sm',
                                    color: '#888888',
                                    flex: 2
                                },
                                {
                                    type: 'text',
                                    text: data.start_date === data.end_date
                                        ? data.start_date
                                        : `${data.start_date} ~ ${data.end_date}`,
                                    size: 'sm',
                                    color: '#333333',
                                    flex: 4
                                }
                            ]
                        },
                        {
                            type: 'box',
                            layout: 'horizontal',
                            contents: [
                                {
                                    type: 'text',
                                    text: '天數',
                                    size: 'sm',
                                    color: '#888888',
                                    flex: 2
                                },
                                {
                                    type: 'text',
                                    text: `${data.days} 天`,
                                    size: 'sm',
                                    color: '#333333',
                                    flex: 4
                                }
                            ]
                        },
                        {
                            type: 'separator',
                            margin: 'md'
                        },
                        {
                            type: 'box',
                            layout: 'horizontal',
                            margin: 'md',
                            spacing: 'sm',
                            contents: [
                                {
                                    type: 'button',
                                    action: {
                                        type: 'postback',
                                        label: '✅ 核准',
                                        data: `action=approve&leave_id=${data.leave_id}&employee_name=${encodeURIComponent(data.employee_name)}&leave_type=${encodeURIComponent(data.leave_type)}`,
                                        displayText: `核准 ${data.employee_name} 的${data.leave_type}申請`
                                    },
                                    style: 'primary',
                                    color: '#198754',
                                    height: 'sm'
                                },
                                {
                                    type: 'button',
                                    action: {
                                        type: 'postback',
                                        label: '❌ 拒絕',
                                        data: `action=reject_prompt&leave_id=${data.leave_id}&employee_name=${encodeURIComponent(data.employee_name)}&leave_type=${encodeURIComponent(data.leave_type)}`,
                                        displayText: `拒絕 ${data.employee_name} 的${data.leave_type}申請`
                                    },
                                    style: 'secondary',
                                    height: 'sm'
                                }
                            ]
                        }
                    ]
                },
                footer: {
                    type: 'box',
                    layout: 'vertical',
                    contents: [{
                        type: 'text',
                        text: '請在下方按鈕直接核准或拒絕',
                        size: 'xs',
                        color: '#aaaaaa',
                        align: 'center'
                    }],
                    paddingAll: '8px'
                }
            }
        };

        await client.pushMessage({
            to: res.data.line_user_id,
            messages: [flexMessage]
        });

        console.log(`FLex Message sent to manager of ${data.department}`);
    } catch (err) {
        console.error('Push leave submitted error:', err.message);
    }
}

// ── Start server ──────────────────────────────────
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Line Bot server running on port ${PORT}`);
    console.log(`Webhook URL: http://localhost:${PORT}/webhook`);
});
