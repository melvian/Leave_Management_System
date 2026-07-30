require('dotenv').config();

const line   = require('@line/bot-sdk');
const express = require('express');
const axios   = require('axios');
const mqtt    = require('mqtt');

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
            `🏖 特別休假剩餘：${d.annual_remaining} 天\n` +
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
                '說明　　 → 顯示此說明'
        }]
    });
}

// ── Postback handler (button taps) ───────────────
async function handlePostback(event) {
    const params   = new URLSearchParams(event.postback.data);
    const action   = params.get('action');
    const leaveId  = params.get('leave_id');
    const empName  = decodeURIComponent(params.get('employee_name') || '');
    const leaveType = decodeURIComponent(params.get('leave_type') || '');

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

    return null;
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
        const res = await axios.get(
            `${process.env.LARAVEL_API}/line/manager`,
            { params: { department: data.department } }
        );

        if (!res.data.line_user_id) {
            console.log(`No Line ID found for manager of ${data.department}`);
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
