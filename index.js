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
    CONFIRM:     'confirm',
};

const LEAVE_TYPES = ['特休假', '病假', '事假', '公假', '補休', '生理假'];

const OVERTIME_STEPS = {
    SELECT_START: 'ot_select_start',
    SELECT_END:   'ot_select_end',
    REASON:       'ot_reason',
    CONFIRM:      'ot_confirm',
};

const DELEGATION_STEPS = {
    SELECT_DELEGATE: 'del_select_delegate',
    START_DELEGATE: 'del_start_delegate',
    END_DELEGATE: 'del_end_delegate',
    REASON: 'del_reason',
    CONFIRM: 'del_confirm',
};

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

    // If user is in a guided flow, handle their input
    if (userState[userId] && text !== '取消') {
        const step = userState[userId].step;

        if (Object.values(OVERTIME_STEPS).includes(step)) {
            return handleOvertimeFlow(event, userId, text);
        }

        if (Object.values(DELEGATION_STEPS).includes(step)){
            return handleDelegationFlow(event, userId, text);
        }
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

    if (text === '我的id' || text === '我的LineID') {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: `您的 Line User ID 是：\n\n${userId}\n\n請將此 ID 提供給人資部，以完成 Line 帳號綁定。` }]
        });
    }

    if (text === '加班' || text === '申請加班') {
        return handleOvertimeStart(event, userId);
    }

    if (text === '我的加班' || text === '加班記錄') {
        return handleMyOvertime(event, userId);
    }

    if (text === '待審' || text === '待審清單' || text === '審核') {
        return handlePendingQueue(event, userId);
    }

    if (text === '代理' || text === '設定代理') {
        return handleDelegationStart(event, userId);
    }

    if (text === '我的代理') {
        return handleMyDelegation(event, userId);
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

            // Auto overtime
            if (d.overtime_minutes && d.overtime_minutes > 0) {

                const otHours = Math.round((d.overtime_minutes / 60) * 10) / 10;
                const startDisplay = d.shift_end; 
                const endDisplay = d.clock_out;
                
                userState[userId] = {
                    step: OVERTIME_STEPS.REASON,
                    auto_overtime: true,
                    start_time: `${d.date}T${startDisplay}`,
                    end_time: `${d.date}T${endDisplay}`,
                    start_display: startDisplay,
                    end_display: endDisplay,
                    date_display: d.date,
                    hours: otHours,
                };

                return client.replyMessage({
                    replyToken: event.replyToken,
                    messages: [
                        { type: 'text', text: msg },
                        {
                            type: 'text',
                            text: `⏰ 系統偵測您今天超時工作 ${otHours} 小時\n （${startDisplay} – ${endDisplay}）\n\n是否記錄加班？`,
                            quickReply: {  
                                items: [ 
                                    {
                                        type: 'action',
                                        action: {
                                            type: 'postback',
                                            label: '✅ 記錄加班',
                                            data: 'action=confirm_overtime',
                                            displayText: '記錄加班'
                                        }
                                    },
                                    {
                                        type: 'action',
                                        action: {
                                            type: 'postback',
                                            label: '❌ 不記錄',
                                            data: 'action=skip_auto_overtime',
                                            displayText: '不記錄'
                                        }
                                    }
                                ]
                            }
                        }
                    ]
                });
            }

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
                '──────────────\n' +
                '上班　　 → 上班打卡\n' +
                '下班　　 → 下班打卡\n' +
                '加班　　 → 記錄加班\n' +
                '請假　　 → 申請請假\n' +
                '取消　　 → 取消目前操作\n' +
                '我的假期 → 查詢假期餘額\n' +
                '我的請假 → 查看請假記錄\n' + 
                '我的加班 → 查看加班記錄\n' +
                '設定代理 → 設定簽核代理人（主管）\n' +
                '我的代理 → 查看目前代理設定（主管）\n' +
                '待審　　 → 查看待審核的申請\n' +
                '我的LineID → 取得您的 Line User ID\n' +
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

async function handleOvertimeStart(event, userId) {
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

    // Init state
    userState[userId] = { step: OVERTIME_STEPS.SELECT_START };

    const today = new Date().toISOString().split('T')[0];
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    const minDate = thirtyDaysAgo.toISOString().split('T')[0];

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'text',
            text: '📋 記錄加班\n請選擇加班開始時間：\n\n（輸入「取消」可隨時取消）',
            quickReply: {
                items: [{
                    type: 'action',
                    action: {
                        type: 'datetimepicker',
                        label: '📋 選擇開始時間',
                        data: 'action=pick_ot_start',
                        mode: 'datetime',
                        initial: `${today}T18:00`,
                        min: `${minDate}T00:00`,
                        max: `${today}T23:30`
                    }
                }]
            }
        }]
    });
}

async function handleOvertimeFlow(event, userId, text) {
    const state = userState[userId];

    if (!state) return null;

    if (state.step === OVERTIME_STEPS.REASON) {
        return handleOvertimeReason(event, userId, text);
    }

    return null;
}

async function handleOvertimeReason(event, userId, text) {
    if (text.trim().length < 2) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 加班原因至少需要2個字，請重新輸入。' }]
        });
    }

    userState[userId].reason = text.trim();
    userState[userId].step   = OVERTIME_STEPS.CONFIRM;

    return sendOvertimeConfirmation(event, userId);
}

async function sendOvertimeConfirmation(event, userId) {
    const s = userState[userId];

    //Calculate total hours
    const [sh,sm] = s.start_time.split('T')[1].split(':').map(Number);
    const [eh,em] = s.end_time.split('T')[1].split(':').map(Number);
    const totalMins = (eh * 60 + em) - (sh * 60 + sm);
    const hours = Math.round((totalMins / 60) * 10) / 10;

    const startDisplay = s.start_time.split('T')[1].substring(0,5);
    const endDisplay = s.end_time.split('T')[1].substring(0,5);
    const dateDisplay = s.start_time.split('T')[0];

    s.hours = hours;
    s.start_display = startDisplay;
    s.end_display = endDisplay;
    s.date_display = dateDisplay;

    const confirmFlex = {
        type: 'flex',
        altText: '請確認您的加班記錄',
        contents: {
            type: 'bubble',
            size: 'kilo',
            header: {
                type: 'box',
                layout: 'vertical',
                contents: [{
                    type: 'text',
                    text: '📋 加班記錄確認',
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
                    makeRow('日期', dateDisplay),
                    makeRow('時段', `${startDisplay} – ${endDisplay}`),
                    makeRow('時數', `${hours} 小時`),
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
                                    label: '✅ 送出加班記錄',
                                    data: `action=submit_overtime`,
                                    displayText: '送出加班記錄'
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
                                    data: `action=cancel_overtime`,
                                    displayText: '取消加班記錄'
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
                    text: '送出後等待主管確認，確認後補休時數自動加入。',
                    size: 'xs',
                    color: '#6c757d',
                    align: 'center',
                    wrap: true
                }],
                paddingAll: '12px'
            }
        }
    };

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [confirmFlex]
    });
}

async function handleOvertimeSubmit(event, userId) {
    const state = userState[userId];

    if (!state || state.step !== OVERTIME_STEPS.CONFIRM) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 沒有可送出的加班記錄，請重新輸入「加班」。' }]
        });
    }

    try {
        const res = await axios.post(
            `${process.env.LARAVEL_API}/line/overtime-submit`,
            {
                line_user_id: userId,
                date: state.date_display,
                start_time: state.start_time,
                end_time: state.end_time,
                hours: state.hours,
                overtime_reason: state.reason,
            }
        );

        delete userState[userId];

        if(res.data.success) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text', text: '✅ 加班記錄已送出，等待主管確認。' }]
            });
        }else {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text', text: `❌記錄失敗： ${res.data.message}` }]
            });
        }

    } catch (err) {
        console.error('Overtime submit error:', err.response?.data || err.message);
        delete userState[userId];
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌記錄失敗，請稍後再試或之系統網頁申請。' }]
        });
    }
}

async function handleOtStartPicked(event, userId, datetime) {
    if (!userState[userId]) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '操作已逾時，請重新輸入「加班」。' }]
        });
    }

    userState[userId].start_time = datetime;
    userState[userId].step       = OVERTIME_STEPS.SELECT_END;

    //End time picker with min = start_time 
    const startDisplay = datetime.split('T')[1].substring(0,5);
    const date = datetime.split('T')[0];
    
    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'text',
            text: `✅ 開始時間：${date} ${startDisplay}\n\n請選擇加班結束時間：`,
            quickReply: {
                items: [{
                    type: 'action',
                    action: {
                        type: 'datetimepicker',
                        label: '📋 選擇結束時間',
                        data: 'action=pick_ot_end',
                        mode: 'datetime',
                        initial: datetime,
                        min: datetime,
                        max: `${date}T23:30`
                    }
                }]
            }
        }]
    });
}

async function handleOtEndPicked(event, userId, datetime) {
    if (!userState[userId]) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '操作已逾時，請重新輸入「加班」。' }]
        });
    }

    const startTime = userState[userId].start_time;
    const endTime   = datetime;

    if (new Date(endTime) <= new Date(startTime)) {
        const date = startTime.split('T')[0];
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{
                type: 'text',
                text: '❌ 結束時間必須晚於開始時間，請重新選擇：',
                quickReply: {
                    items: [{
                        type: 'action',
                        action: {
                            type: 'datetimepicker',
                            label: '📋 重新選擇結束時間',
                            data: 'action=pick_ot_end',
                            mode: 'datetime',
                            initial: startTime,
                            min: startTime,
                            max: `${date}T23:30`
                        }
                    }]
                }
            }]
        });
    }

    // Calculate total hours
    const [sh, sm] = startTime.split('T')[1].split(':').map(Number);
    const [eh, em] = endTime.split('T')[1].split(':').map(Number);
    const totalMins  = (eh * 60 + em) - (sh * 60 + sm);
    const hours = Math.round((totalMins / 60) * 10) / 10;

    const endDisplay = endTime.split('T')[1].substring(0,5);

    userState[userId].end_time = endTime;
    userState[userId].step     = OVERTIME_STEPS.REASON;

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'text',
            text: `✅ 結束時間：${endDisplay}\n⏱ 加班時數：${hours} 小時\n\n請輸入加班原因：`
        }]
    });
}

//Auto overtime after clock out
async function handleAutoOvertimeConfirm(event, userId) {
    const state = userState[userId];

    if (!state || !state.auto_overtime) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '請輸入加班原因：' }]
        });
    }

    // Go to reason step
    userState[userId].step = OVERTIME_STEPS.REASON;

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{ type: 'text', text: `加班時段：${state.start_display} - ${state.end_display} (${state.hours} 小時) \n\n請輸入加班原因：` }]
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

async function handleMyOvertime(event, userId) {
    try {
        const res = await axios.get(
            `${process.env.LARAVEL_API}/line/my-overtime`,
            { params: { line_user_id: userId } }
        );

        const d = res.data;

        if (!d.success) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text', text: `❌ ${d.message}` }]
            });
        }

        if (!d.records || d.records.length === 0) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: `${d.name} 目前沒有加班記錄。` }]
            });
        }

        const statusEmoji = {
            '待確認': '⏳',
            '已確認': '✅',
            '已駁回': '❌',
        };

        const bubbles = d.records.map(r => ({
            type: 'bubble',
            size: 'kilo',
            header: {
                type: 'box',
                layout: 'horizontal',
                contents: [
                    {
                        type: 'text',
                        text: `⏰ ${r.date}`,
                        color: '#ffffff',
                        size: 'sm',
                        weight: 'bold',
                        flex: 3
                    },
                    {
                        type: 'text',
                        text: `${statusEmoji[r.status] || '❓'} ${r.status}`,
                        color: '#ffffff',
                        size: 'sm',
                        align: 'end',
                        flex: 2
                    }
                ],
                backgroundColor: r.status === '已確認' ? '#198754'
                    : r.status === '已駁回'            ? '#dc3545'
                    : '#0E7C86',
                paddingAll: '12px'
            },
            body: {
                type: 'box',
                layout: 'vertical',
                spacing: 'sm',
                paddingAll: '12px',
                contents: [
                    makeRow('時段', `${r.start_time} – ${r.end_time}`),
                    makeRow('時數', `${r.hours} 小時`),
                    makeRow('事由', r.overtime_reason || '—'),
                    ...(r.admin_note ? [makeRow('備註', r.admin_note)] : []),
                ]
            }
        }));

        const contents = bubbles.length === 1
            ? bubbles[0]
            : { type: 'carousel', contents: bubbles };

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [
                { type: 'text', text: `${d.name} 的最近加班記錄（最多5筆）：` },
                { type: 'flex', altText: '您的加班記錄', contents }
            ]
        });

    } catch (err) {
        console.error('My overtime error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 查詢失敗，請稍後再試。' }]
        });
    }
}

// ── Delegation flow ──────────────────────────────
async function handleDelegationStart(event,userId){
    console.log('LARAVEL_API:', process.env.LARAVEL_API);
    //Is user a manager
    try {
        const res = await axios.get(
            `${process.env.LARAVEL_API}/line/my-profile`,
            {params:{line_user_id: userId}}
        );

        console.log('my-profile response:', res.data);

        if (!res.data.success) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: '❌ 找不到您的員工帳號。' }]
            });
        }

        if (res.data.role !== '部門主管') {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: '❌ 只有部門主管可以設定簽核代理。' }]
            });
        }

        userState[userId] = {
            step:          DELEGATION_STEPS.SELECT_DELEGATE,
            delegator_id:  res.data.employee_id,
            delegator_name: res.data.name,
        };

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '👤 設定簽核代理\n\n請輸入代理人的員工編號\n（例如：E00068）\n\n輸入「取消」可隨時取消' }]
        });

    } catch (err) {
        console.error('Delegation start error:', err.message);
        console.error('Full error:', err);
        console.error('Stack:', err.stack);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 發生錯誤，請稍後再試。' }]
        });
    }
}

async function handleDelegationFlow(event, userId, text) {
    const state = userState[userId];
    if (!state) return null;

    if (state.step === DELEGATION_STEPS.SELECT_DELEGATE) {
        return handleDelegateEmployeeNo(event, userId, text);
    }
    if (state.step === DELEGATION_STEPS.REASON) {
        if (text.trim() === '略過') {
            userState[userId].reason='';
            return handleDelegationReason(event, userId, '');
        }
        return handleDelegationReason(event, userId, text);
    }
    return null;
}

async function handleDelegateEmployeeNo(event, userId, text) {
    const empNo = text.trim().toUpperCase();

    try {
        // Look up employee by employee_no
        const res = await axios.get(
            `${process.env.LARAVEL_API}/line/employee-by-no`,
            { params: { employee_no: empNo } }
        );

        if (!res.data.success) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: `❌ 找不到員工編號 ${empNo}，請重新輸入：` }]
            });
        }

        // Can't delegate to yourself
        if (res.data.employee_id === userState[userId].delegator_id) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: '❌ 不能將自己設為代理人，請輸入其他員工編號：' }]
            });
        }

        userState[userId].delegate_id   = res.data.employee_id;
        userState[userId].delegate_name = res.data.name;
        userState[userId].step          = DELEGATION_STEPS.START_DATE;

        const today = new Date().toISOString().split('T')[0];

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{
                type: 'text',
                text: `✅ 代理人：${res.data.name}（${empNo}）\n\n請選擇代理開始日期：`,
                quickReply: {
                    items: [{
                        type: 'action',
                        action: {
                            type: 'datetimepicker',
                            label: '📅 選擇開始日期',
                            data: 'action=pick_del_start',
                            mode: 'date',
                            initial: today,
                            min: today,
                            max: '2027-12-31'
                        }
                    }]
                }
            }]
        });

    } catch (err) {
        console.error('Employee lookup error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 查詢失敗，請重新輸入員工編號：' }]
        });
    }
}

async function handleDelegationReason(event, userId, text) {
    userState[userId].reason = text.trim() || '（未填寫）';
    userState[userId].step   = DELEGATION_STEPS.CONFIRM;

    const s = userState[userId];

    const confirmFlex = {
        type: 'flex',
        altText: '請確認簽核代理設定',
        contents: {
            type: 'bubble',
            size: 'kilo',
            header: {
                type: 'box',
                layout: 'vertical',
                contents: [{
                    type: 'text',
                    text: '👤 簽核代理確認',
                    color: '#ffffff',
                    size: 'md',
                    weight: 'bold'
                }],
                backgroundColor: '#6A1B9A',
                paddingAll: '16px'
            },
            body: {
                type: 'box',
                layout: 'vertical',
                spacing: 'sm',
                paddingAll: '16px',
                contents: [
                    makeRow('委託人', s.delegator_name),
                    makeRow('代理人', s.delegate_name),
                    makeRow('開始日期', s.start_date),
                    makeRow('結束日期', s.end_date),
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
                                    label: '✅ 確認',
                                    data: 'action=submit_delegation',
                                    displayText: '確認設定代理'
                                },
                                style: 'primary',
                                color: '#6A1B9A',
                                height: 'sm'
                            },
                            {
                                type: 'button',
                                action: {
                                    type: 'postback',
                                    label: '❌ 取消',
                                    data: 'action=cancel_delegation',
                                    displayText: '取消代理設定'
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
                    text: `代理期間 ${s.delegate_name} 將擁有您的審核權限`,
                    size: 'xs',
                    color: '#aaaaaa',
                    align: 'center',
                    wrap: true
                }],
                paddingAll: '8px'
            }
        }
    };

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [confirmFlex]
    });
}

async function handleDelegationSubmit(event, userId) {
    const state = userState[userId];

    if (!state || state.step !== DELEGATION_STEPS.CONFIRM) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 操作已逾時，請重新輸入「設定代理」。' }]
        });
    }

    try {
        const res = await axios.post(
            `${process.env.LARAVEL_API}/line/delegation-set`,
            {
                line_user_id: userId,
                delegate_id:  state.delegate_id,
                start_date:   state.start_date,
                end_date:     state.end_date,
                reason:       state.reason,
            }
        );

        delete userState[userId];

        if (res.data.success) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: `✅ 簽核代理已設定！\n\n代理人：${state.delegate_name}\n期間：${state.start_date} ~ ${state.end_date}\n\n代理期間，${state.delegate_name} 將可代您審核請假與加班申請。` }]
            });
        } else {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: `❌ 設定失敗：${res.data.message}` }]
            });
        }

    } catch (err) {
        console.error('Delegation submit error:', err.message);
        delete userState[userId];
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 設定失敗，請稍後再試。' }]
        });
    }
}

async function handleMyDelegation(event, userId) {
    try {
        const res = await axios.get(
            `${process.env.LARAVEL_API}/line/my-delegations`,
            { params: { line_user_id: userId } }
        );

        const d = res.data;

        if (!d.success) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text', text: `❌ ${d.message}` }]
            });
        }

        if (!d.delegations || d.delegations.length === 0) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: '目前沒有有效的簽核代理設定。\n\n輸入「設定代理」可新增代理。' }]
            });
        }

        const bubbles = d.delegations.map(del => ({
            type: 'bubble',
            size: 'kilo',
            header: {
                type: 'box',
                layout: 'vertical',
                contents: [{
                    type: 'text',
                    text: del.is_active_now ? '🟢 代理中' : '⏳ 待生效',
                    color: '#ffffff',
                    size: 'sm',
                    weight: 'bold'
                }],
                backgroundColor: del.is_active_now ? '#198754' : '#6A1B9A',
                paddingAll: '12px'
            },
            body: {
                type: 'box',
                layout: 'vertical',
                spacing: 'sm',
                paddingAll: '12px',
                contents: [
                    makeRow('代理人', del.delegate_name),
                    makeRow('期間', `${del.start_date} ~ ${del.end_date}`),
                    makeRow('原因', del.reason || '—'),
                    { type: 'separator', margin: 'md' },
                    {
                        type: 'button',
                        action: {
                            type: 'postback',
                            label: '❌ 撤銷此代理',
                            data: `action=revoke_delegation&delegation_id=${del.id}&delegate_name=${encodeURIComponent(del.delegate_name)}`,
                            displayText: `撤銷 ${del.delegate_name} 的代理`
                        },
                        style: 'secondary',
                        height: 'sm',
                        margin: 'md'
                    }
                ]
            }
        }));

        const contents = bubbles.length === 1
            ? bubbles[0]
            : { type: 'carousel', contents: bubbles };

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [
                { type: 'text', text: '您目前的簽核代理設定：' },
                { type: 'flex', altText: '簽核代理設定', contents }
            ]
        });

    } catch (err) {
        console.error('My delegation error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌查詢失敗，請稍後再試。' }]
        });
    }
}

async function handleDelStartPicked(event, userId, date) {
    if (!userState[userId]) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '操作已逾時，請重新輸入「設定代理」。' }]
        });
    }

    userState[userId].start_date = date;
    userState[userId].step       = DELEGATION_STEPS.END_DATE;

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{
            type: 'text',
            text: `✅ 開始日期：${date}\n\n請選擇代理結束日期：`,
            quickReply: {
                items: [{
                    type: 'action',
                    action: {
                        type: 'datetimepicker',
                        label: '📅 選擇結束日期',
                        data: 'action=pick_del_end',
                        mode: 'date',
                        initial: date,
                        min: date,
                        max: '2027-12-31'
                    }
                }]
            }
        }]
    });
}


async function handleDelEndPicked(event, userId, date) {
    if (!userState[userId]) {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '操作已逾時，請重新輸入「設定代理」。' }]
        });
    }

    const startDate = new Date(userState[userId].start_date);
    const endDate   = new Date(date);

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
                            data: 'action=pick_del_end',
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

    userState[userId].end_date = date;
    userState[userId].step     = DELEGATION_STEPS.REASON;

    return client.replyMessage({
        replyToken: event.replyToken,
        messages: [{ type: 'text',
            text: `✅ 結束日期：${date}\n\n請輸入代理原因（例如：出差、請假）\n\n或直接輸入「略過」跳過此步驟：` }]
    });
}


async function handleRevokeDelegation(event, userId, delegationId, delegateName) {
    try {
        const res = await axios.post(
            `${process.env.LARAVEL_API}/line/delegation-revoke`,
            {
                line_user_id:  userId,
                delegation_id: delegationId,
            }
        );

        const msg = res.data.success
            ? `✅ 已撤銷 ${delegateName} 的簽核代理`
            : `❌ 撤銷失敗：${res.data.message}`;

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: msg }]
        });

    } catch (err) {
        console.error('Revoke delegation error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 操作失敗，請稍後再試。' }]
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

    // Leave handler
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

    // Overtime handler
    if (action === 'pick_ot_start') {
        const datetime = event.postback.params.datetime;
        return handleOtStartPicked(event, userId, datetime);
    }

    if (action === 'pick_ot_end') {
        const datetime = event.postback.params.datetime;
        return handleOtEndPicked(event, userId, datetime);
    }
    
    if (action === 'submit_overtime') {
        return handleOvertimeSubmit(event, userId);
    }

    if (action === 'cancel_overtime') {
        delete userState[userId];
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '已取消加班記錄。' }]
        });
    }

    if (action === 'confirm_auto_overtime') {
        return handleAutoOvertimeConfirm(event, userId);
    }

    if (action === 'skip_auto_overtime') {
        delete userState[userId];
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '已跳過自動加班記錄。' }]
        });
    }

    if (action === 'confirm_overtime') {
        const overtimeId = params.get('overtime_id');
        const empName = decodeURIComponent(params.get('employee_name') || '');
        const hours = params.get('hours');
        return handleOvertimeConfirm(event, overtimeId, empName, hours);
    }

    if (action === 'reject_overtime') {
        const overtimeId = params.get('overtime_id');
        const empName = decodeURIComponent(params.get('employee_name') || '');
        return handleOvertimeReject(event, overtimeId, empName);
    }

    // Delegation handler
    if (action === 'pick_del_start') {
        const date = event.postback.params.date;
        return handleDelStartPicked (event, userId, date);
    }

    if (action === 'pick_del_end') {
        const date = event.postback.params.date;
        return handleDelEndPicked (event, userId, date);
    }

    if (action === 'submit_delegation') {
        return handleDelegationSubmit (event, userId);
    }

    if (action === 'cancel_delegation') {
        delete userState[userId];
        return client.replyMessage({
            replyToken: event,replyToken,
            messages: [{ type: 'text', text: '已取消代理設定。'}]
        });
    }

    if (action === 'revoke_delegation') {
        const delegationId = params.get('delegation_id');
        const delegateName = decodeURIComponent(params.get('delegate_name'));
        return handleRevokeDelegation(event, userId, delegationId, delegateName);
    }

    if (action === 'start_delegation_flow') {
        return handleDelegationStart(event, userId);
    }

    if (action === 'skip_delegation') {
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '好的，若之後需要設定代理，可隨時輸入「設定代理」。' }]
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

        // ── If approved person is a manager, ask about delegation ──
        if (res.data.role === '部門主管') {
            // Small delay so messages don't stack instantly
            setTimeout(async () => {
                try {
                    await client.pushMessage({
                        to: res.data.line_user_id,
                        messages: [{
                            type: 'text',
                            text: `📋 您即將請假（${data.start_date} ~ ${data.end_date}）\n\n是否需要設定簽核代理人？代理人可在您請假期間代為審核請假與加班申請。`,
                            quickReply: {
                                items: [
                                    {
                                        type: 'action',
                                        action: {
                                            type: 'postback',
                                            label: '✅ 設定代理人',
                                            data: 'action=start_delegation_flow',
                                            displayText: '設定簽核代理人'
                                        }
                                    },
                                    {
                                        type: 'action',
                                        action: {
                                            type: 'postback',
                                            label: '❌ 不需要',
                                            data: 'action=skip_delegation',
                                            displayText: '不需要設定代理'
                                        }
                                    }
                                ]
                            }
                        }]
                    });
                } catch (err) {
                    console.error('Push delegation prompt error:', err.message);
                }
            }, 1500);
        }

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

// ── Pending queue for managers and HR ─────────────
async function handlePendingQueue(event, userId) {
    try {
        // Fetch pending leaves and overtime in parallel
        const [leaveRes, otRes] = await Promise.all([
            axios.get(`${process.env.LARAVEL_API}/line/pending-leaves`,
                { params: { line_user_id: userId } }).catch(() => null),
            axios.get(`${process.env.LARAVEL_API}/line/pending-overtime`,
                { params: { line_user_id: userId } }).catch(() => null),
        ]);

        // Check access
        if (!leaveRes?.data?.success && !otRes?.data?.success) {
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: '❌ 您沒有審核權限。' }]
            });
        }

        const leaves  = leaveRes?.data?.leaves  || [];
        const records = otRes?.data?.records    || [];
        const role    = leaveRes?.data?.role    || otRes?.data?.role;
        const dept    = leaveRes?.data?.department || '';

        // Nothing pending
        if (leaves.length === 0 && records.length === 0) {
            const roleLabel = role === '人資部' || role === '系統管理者'
                ? '（人資）' : `（${dept}）`;
            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text',
                    text: `✅ 目前沒有待審核的申請${roleLabel}。` }]
            });
        }

        const messages = [];

        // ── Leave pending cards ───────────────────
        if (leaves.length > 0) {
            const leaveBubbles = leaves.map(leave => {
                const isHR = role === '人資部' || role === '系統管理者';
                const dateStr = leave.start_date === leave.end_date
                    ? leave.start_date
                    : `${leave.start_date} ~ ${leave.end_date}`;

                const actionButtons = isHR
                    // HR sees approve/reject
                    ? [
                        {
                            type: 'button',
                            action: {
                                type: 'postback',
                                label: '✅ 核准',
                                data: `action=approve&leave_id=${leave.id}&employee_name=${encodeURIComponent(leave.employee_name)}&leave_type=${encodeURIComponent(leave.leave_type)}`,
                                displayText: `核准 ${leave.employee_name} 的${leave.leave_type}`
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
                                data: `action=reject_prompt&leave_id=${leave.id}&employee_name=${encodeURIComponent(leave.employee_name)}&leave_type=${encodeURIComponent(leave.leave_type)}`,
                                displayText: `拒絕 ${leave.employee_name} 的${leave.leave_type}`
                            },
                            style: 'secondary',
                            height: 'sm'
                        }
                    ]
                    // Manager sees approve/reject
                    : [
                        {
                            type: 'button',
                            action: {
                                type: 'postback',
                                label: '✅ 核准',
                                data: `action=approve&leave_id=${leave.id}&employee_name=${encodeURIComponent(leave.employee_name)}&leave_type=${encodeURIComponent(leave.leave_type)}`,
                                displayText: `核准 ${leave.employee_name} 的${leave.leave_type}`
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
                                data: `action=reject_prompt&leave_id=${leave.id}&employee_name=${encodeURIComponent(leave.employee_name)}&leave_type=${encodeURIComponent(leave.leave_type)}`,
                                displayText: `拒絕 ${leave.employee_name} 的${leave.leave_type}`
                            },
                            style: 'secondary',
                            height: 'sm'
                        }
                    ];

                return {
                    type: 'bubble',
                    size: 'kilo',
                    header: {
                        type: 'box',
                        layout: 'horizontal',
                        contents: [
                            {
                                type: 'text',
                                text: '📋 待審請假',
                                color: '#ffffff',
                                size: 'sm',
                                weight: 'bold',
                                flex: 3
                            },
                            {
                                type: 'text',
                                text: leave.leave_type,
                                color: '#ffd700',
                                size: 'sm',
                                align: 'end',
                                flex: 2
                            }
                        ],
                        backgroundColor: '#1F3864',
                        paddingAll: '12px'
                    },
                    body: {
                        type: 'box',
                        layout: 'vertical',
                        spacing: 'sm',
                        paddingAll: '12px',
                        contents: [
                            makeRow('申請人',
                                `${leave.employee_name}（${leave.employee_no}）`),
                            makeRow('部門', leave.department),
                            makeRow('日期', dateStr),
                            makeRow('天數', `${leave.days} 天`),
                            makeRow('事由', leave.leave_reason || '—'),
                            { type: 'separator', margin: 'md' },
                            {
                                type: 'box',
                                layout: 'horizontal',
                                margin: 'md',
                                spacing: 'sm',
                                contents: actionButtons
                            }
                        ]
                    }
                };
            });

            // Show as carousel if multiple
            messages.push({
                type: 'text',
                text: `📋 待審請假（${leaves.length} 筆）：`
            });
            messages.push({
                type: 'flex',
                altText: `待審請假 ${leaves.length} 筆`,
                contents: leaves.length === 1
                    ? leaveBubbles[0]
                    : { type: 'carousel', contents: leaveBubbles }
            });
        }

        // ── Overtime pending cards ─────────────────
        if (records.length > 0) {
            const otBubbles = records.map(r => ({
                type: 'bubble',
                size: 'kilo',
                header: {
                    type: 'box',
                    layout: 'horizontal',
                    contents: [
                        {
                            type: 'text',
                            text: '⏰ 待確認加班',
                            color: '#ffffff',
                            size: 'sm',
                            weight: 'bold',
                            flex: 3
                        },
                        {
                            type: 'text',
                            text: `${r.hours}h`,
                            color: '#ffd700',
                            size: 'sm',
                            align: 'end',
                            flex: 2
                        }
                    ],
                    backgroundColor: '#0E7C86',
                    paddingAll: '12px'
                },
                body: {
                    type: 'box',
                    layout: 'vertical',
                    spacing: 'sm',
                    paddingAll: '12px',
                    contents: [
                        makeRow('員工',
                            `${r.employee_name}（${r.employee_no}）`),
                        makeRow('部門', r.department),
                        makeRow('日期', r.date),
                        makeRow('時段', `${r.start_time} – ${r.end_time}`),
                        makeRow('時數', `${r.hours} 小時`),
                        makeRow('事由', r.overtime_reason || '—'),
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
                                        label: '✅ 確認',
                                        data: `action=confirm_overtime&overtime_id=${r.id}&employee_name=${encodeURIComponent(r.employee_name)}&hours=${r.hours}`,
                                        displayText: `確認 ${r.employee_name} 的加班`
                                    },
                                    style: 'primary',
                                    color: '#0E7C86',
                                    height: 'sm'
                                },
                                {
                                    type: 'button',
                                    action: {
                                        type: 'postback',
                                        label: '❌ 拒絕',
                                        data: `action=reject_overtime&overtime_id=${r.id}&employee_name=${encodeURIComponent(r.employee_name)}`,
                                        displayText: `拒絕 ${r.employee_name} 的加班`
                                    },
                                    style: 'secondary',
                                    height: 'sm'
                                }
                            ]
                        }
                    ]
                }
            }));

            messages.push({
                type: 'text',
                text: `⏰ 待確認加班（${records.length} 筆）：`
            });
            messages.push({
                type: 'flex',
                altText: `待確認加班 ${records.length} 筆`,
                contents: records.length === 1
                    ? otBubbles[0]
                    : { type: 'carousel', contents: otBubbles }
            });
        }

        return client.replyMessage({
            replyToken: event.replyToken,
            messages: messages.slice(0, 5) // Line max 5 messages per reply
        });

    } catch (err) {
        console.error('Pending queue error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text',
                text: '❌ 查詢失敗，請稍後再試。' }]
        });
    }
}

async function handleOvertimeConfirm(event, overtimeId, empName, hours) {
    try {
        const res = await axios.post(
            `${process.env.LARAVEL_API}/line/overtime-confirm`,
            {
                overtime_id: overtimeId,
                manager_line_id: event.source.userId,
            }
        );
        
        const msg = res.data.success
            ? `✅ 已確認 ${empName} 的加班記錄（${hours} 小時）`
            : `❌ 操作失敗：${res.data.message}`; 

            return client.replyMessage({
                replyToken: event.replyToken,
                messages: [{ type: 'text', text: msg }]
            });

    } catch (err) {
        console.error('Overtime confirm error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 操作失敗，請至系統網頁確認。' }]
        });
    }
}

async function handleOvertimeReject(event, overtimeId, empName) {
    try {
        const res = await axios.post(
            `${process.env.LARAVEL_API}/line/overtime-reject`,
            {
                overtime_id: overtimeId,
                manager_line_id: event.source.userId,
                admin_note: '主管透過 Line 拒絕，詳情請至系統查看。'
            }
        );
        
        const msg = res.data.success
            ? `❌ 已拒絕 ${empName} 的加班記錄`
            : `❌ 操作失敗：${res.data.message}`;    
            
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: msg }]
        });

    } catch (err) {
        console.error('Overtime reject error:', err.message);
        return client.replyMessage({
            replyToken: event.replyToken,
            messages: [{ type: 'text', text: '❌ 操作失敗，請至系統網頁確認。' }]
        });
    }
}

// ── Start server ──────────────────────────────────
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Line Bot server running on port ${PORT}`);
    console.log(`Webhook URL: http://localhost:${PORT}/webhook`);
});