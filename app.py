import tkinter as tk
from tkinter import ttk
import paho.mqtt.client as mqtt
import json
import threading
import time
from datetime import datetime
import pystray
from PIL import Image, ImageDraw, ImageFont
import plyer

# ── Config ───────────────────────────────────────────
BROKER_HOST      = "localhost"
BROKER_PORT      = 1883
RECONNECT_DELAY  = 5   # seconds between reconnect attempts

TOPICS = [
    "attendance/#",
    "leave/#",
    "overtime/#",
]


# ── Build tray clock icon image ────────────────────────────
def make_tray_icon():
    img = Image.new("RGBA", (64, 64), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    # Blue circle
    draw.ellipse([4, 4, 60, 60], fill=(31, 56, 100))

    # Clock outline
    draw.ellipse([16, 16, 48, 48], outline="white", width=3)

    # Clock hands
    draw.line((32, 32, 32, 22), fill="white", width=3)
    draw.line((32, 32, 40, 38), fill="white", width=3)

    return img


# ── Toast notification helper ────────────────────────
def toast(title: str, message: str):
    try:
        plyer.notification.notify(
            title=title,
            message=message,
            app_name="Attendance Monitor",
            timeout=4,
        )
    except Exception:
        pass   # toast failure never breaks the app


# ── Main App ─────────────────────────────────────────
class AttendanceMonitor(tk.Tk):

    def __init__(self):
        super().__init__()
        self.title("Attendance Monitor")
        self.geometry("960x680")
        self.configure(bg="#1F3864")
        self.resizable(True, True)

        self.events          = []
        self._mqtt_connected = False
        self._reconnecting   = False
        self._quit_flag      = False

        # Store all attendance data for dept filter
        self._all_att_data   = []
        self._departments    = set()

        self._build_ui()
        self._connect_mqtt()
        self._setup_tray()

        # Intercept close button → minimise to tray
        self.protocol("WM_DELETE_WINDOW", self._hide_to_tray)


    # ── UI ───────────────────────────────────────────
    def _build_ui(self):

        # Header
        header = tk.Frame(self, bg="#1F3864", pady=10)
        header.pack(fill="x", padx=20)

        tk.Label(header, text="Attendance Monitor",
                 font=("TkDefaultFont", 16, "bold"),
                 fg="white", bg="#1F3864").pack(side="left")

        self.conn_label = tk.Label(header,
                 text="● Connected",
                 font=("TkDefaultFont", 11),
                 fg="#4ade80", bg="#1F3864")
        self.conn_label.pack(side="right")

        self.time_label = tk.Label(header, text="",
                 font=("TkDefaultFont", 11),
                 fg="#93c5fd", bg="#1F3864")
        self.time_label.pack(side="right", padx=20)
        self._update_clock()

        # Stats row
        stats_frame = tk.Frame(self, bg="#1F3864", pady=5)
        stats_frame.pack(fill="x", padx=20)

        self.stat_vars = {
            "clocked_in": tk.StringVar(value="0"),
            "late":       tk.StringVar(value="0"),
            "leave":      tk.StringVar(value="0"),
            "Absent":    tk.StringVar(value="0"),
        }

        for label, key, color in [
            ("Clocked In", "clocked_in", "#4ade80"),
            ("Late",       "late",       "#facc15"),
            ("On Leave",   "leave",      "#60a5fa"),
            ("Absent",     "Absent",     "#f87171"),
        ]:
            card = tk.Frame(stats_frame, bg="#2E74B5",
                           relief="flat", padx=15, pady=8)
            card.pack(side="left", padx=5)
            tk.Label(card, text=label,
                    font=("TkDefaultFont", 9),
                    fg="#93c5fd", bg="#2E74B5").pack()
            tk.Label(card, textvariable=self.stat_vars[key],
                    font=("TkDefaultFont", 22, "bold"),
                    fg=color, bg="#2E74B5").pack()

        # Tabs
        style = ttk.Style()
        style.theme_use("default")
        style.configure("TNotebook",
                        background="#1F3864", borderwidth=0)
        style.configure("TNotebook.Tab",
                        background="#2E74B5", foreground="white",
                        padding=[12, 5],
                        font=("TkDefaultFont", 10))
        style.map("TNotebook.Tab",
                  background=[("selected", "#0E7C86")])

        notebook = ttk.Notebook(self)
        notebook.pack(fill="both", expand=True, padx=20, pady=10)

        feed_frame  = tk.Frame(notebook, bg="#f4f7fb")
        att_frame   = tk.Frame(notebook, bg="#f4f7fb")
        leave_frame = tk.Frame(notebook, bg="#f4f7fb")

        notebook.add(feed_frame,  text="  Live Feed  ")
        notebook.add(att_frame,   text="  Today's Attendance  ")
        notebook.add(leave_frame, text="  Pending Leave Applications  ")

        self._build_feed_tab(feed_frame)
        self._build_attendance_tab(att_frame)
        self._build_leave_tab(leave_frame)


    def _build_feed_tab(self, parent):
        self.feed_text = tk.Text(
            parent,
            font=("TkFixedFont", 10),
            bg="#0f172a", fg="#e2e8f0",
            relief="flat", padx=10, pady=10,
            wrap="word"
        )
        self.feed_text.pack(fill="both", expand=True)
        self.feed_text.config(state="disabled")

        for tag, color in [
            ("clock_in",       "#4ade80"),
            ("clock_out",      "#60a5fa"),
            ("late",           "#facc15"),
            ("leave_submitted","#c084fc"),
            ("leave_approved", "#4ade80"),
            ("leave_rejected", "#f87171"),
            ("overtime",       "#fb923c"),
            ("time",           "#64748b"),
            ("reconnect",      "#fb923c"),
        ]:
            self.feed_text.tag_config(tag, foreground=color)


    def _build_attendance_tab(self, parent):
        # ── Department filter bar ────────────────────
        filter_bar = tk.Frame(parent, bg="#f4f7fb", pady=6)
        filter_bar.pack(fill="x", padx=8)

        tk.Label(filter_bar, text="Department Filter:",
                 font=("TkDefaultFont", 10),
                 bg="#f4f7fb").pack(side="left")

        self.dept_var = tk.StringVar(value="All Departments")
        self.dept_combo = ttk.Combobox(
            filter_bar,
            textvariable=self.dept_var,
            values=["All Departments"],
            state="readonly",
            width=16,
            font=("TkDefaultFont", 10)
        )
        self.dept_combo.pack(side="left", padx=6)
        self.dept_combo.bind("<<ComboboxSelected>>",
                             lambda e: self._apply_dept_filter())

        # Clear filter button
        tk.Button(filter_bar, text="Clear Filter",
                  font=("TkDefaultFont", 9),
                  bg="#e2e8f0", relief="flat", padx=8,
                  command=self._clear_dept_filter
                  ).pack(side="left")

        # ── Treeview ─────────────────────────────────
        cols = ("Employee ID", "Name", "Department",
                "Clock In", "Clock Out", "Hours", "Status")
        self.att_tree = ttk.Treeview(
            parent, columns=cols,
            show="headings", height=20
        )
        for col, w in zip(cols, [90,100,100,100,100,70,80]):
            self.att_tree.heading(col, text=col)
            self.att_tree.column(col, width=w, anchor="center")

        scroll = ttk.Scrollbar(parent, orient="vertical",
                               command=self.att_tree.yview)
        self.att_tree.configure(yscrollcommand=scroll.set)
        self.att_tree.pack(side="left", fill="both", expand=True)
        scroll.pack(side="right", fill="y")

        self.att_tree.tag_configure("late",
                                    background="#fef9c3")
        self.att_tree.tag_configure("early_leave",
                                    background="#ffedd5")
        self.att_tree.tag_configure("normal",
                                    background="#d1fae5")

        self.att_rows  = {}   # employee_id → row_id
        self.att_data  = {}   # employee_id → data dict


    def _build_leave_tab(self, parent):
        cols = ("Leave ID","Employee","Department",
                "Leave Type","Start","End","Days","Status")
        self.leave_tree = ttk.Treeview(
            parent, columns=cols,
            show="headings", height=20
        )
        for col, w in zip(cols, [60,90,90,80,90,90,50,70]):
            self.leave_tree.heading(col, text=col)
            self.leave_tree.column(col, width=w, anchor="center")

        scroll = ttk.Scrollbar(parent, orient="vertical",
                               command=self.leave_tree.yview)
        self.leave_tree.configure(yscrollcommand=scroll.set)
        self.leave_tree.pack(side="left", fill="both", expand=True)
        scroll.pack(side="right", fill="y")

        self.leave_tree.tag_configure("pending",  background="#fef9c3")
        self.leave_tree.tag_configure("approved", background="#d1fae5")
        self.leave_tree.tag_configure("rejected", background="#fee2e2")


    # ── Department filter helpers ─────────────────────
    def _update_dept_combo(self, dept: str):
        """Add new department to combo if not already there."""
        self._departments.add(dept)
        values = ["All Departments"] + sorted(self._departments)
        self.dept_combo["values"] = values


    def _apply_dept_filter(self):
        selected = self.dept_var.get()
        # Clear tree
        for row in self.att_tree.get_children():
            self.att_tree.delete(row)
        self.att_rows = {}
        # Re-insert filtered data
        for emp_id, d in self.att_data.items():
            if selected == "All Departments" or d["department"] == selected:
                tag    = d.get("tag", "normal")
                row_id = self.att_tree.insert(
                    "", "end",
                    values=(
                        d["employee_no"], d["name"],
                        d["department"],
                        d.get("clock_in", "—"),
                        d.get("clock_out", "—"),
                        d.get("worked_hours", "—"),
                        d.get("status_label", ""),
                    ),
                    tags=(tag,)
                )
                self.att_rows[emp_id] = row_id


    def _clear_dept_filter(self):
        self.dept_var.set("All Departments")
        self._apply_dept_filter()


    # ── MQTT ─────────────────────────────────────────
    def _connect_mqtt(self):
        self._mqtt = mqtt.Client(
            mqtt.CallbackAPIVersion.VERSION2,
            client_id="exe-monitor-" + str(id(self)),
            clean_session=True
        )
        self._mqtt.on_connect    = self._on_connect
        self._mqtt.on_disconnect = self._on_disconnect
        self._mqtt.on_message    = self._on_message

        self._try_connect()


    def _try_connect(self):
        if self._quit_flag:
            return
        try:
            self._mqtt.connect(BROKER_HOST, BROKER_PORT, keepalive=60)
            thread = threading.Thread(
                target=self._mqtt.loop_forever, daemon=True)
            thread.start()
        except Exception:
            self.after(0, lambda: self.conn_label.config(
                text="● Connection Failed. Reconnecting...", fg="#f87171"))
            self._schedule_reconnect()


    def _schedule_reconnect(self):
        if self._quit_flag or self._reconnecting:
            return
        self._reconnecting = True

        def attempt():
            if self._quit_flag:
                return
            self._reconnecting = False
            try:
                self._mqtt.reconnect()
            except Exception:
                self.after(0, lambda: self.conn_label.config(
                    text=f"● Reconnecting... ({RECONNECT_DELAY}s)",
                    fg="#fb923c"))
                timer = threading.Timer(
                    RECONNECT_DELAY, attempt)
                timer.daemon = True
                timer.start()

        timer = threading.Timer(RECONNECT_DELAY, attempt)
        timer.daemon = True
        timer.start()


    def _on_connect(self, client, userdata, flags, reason_code, properties):
        if reason_code == 0:
            self._mqtt_connected = True
            self._reconnecting   = False
            self.after(0, lambda: self.conn_label.config(
                text="● Connected", fg="#4ade80"))
            for topic in TOPICS:
                client.subscribe(topic, qos=1)
            # Feed notice on reconnect (only after first connect)
            if hasattr(self, '_was_connected'):
                self.after(0, self._feed_reconnect_notice)
            self._was_connected = True
        else:
            self.after(0, lambda: self.conn_label.config(
                text="● Connection Failed", fg="#f87171"))


    def _on_disconnect(self, client, userdata, flags, reason_code, properties):
        self._mqtt_connected = False
        if not self._quit_flag:
            self.after(0, lambda: self.conn_label.config(
                text="● Disconnected. Reconnecting...", fg="#f87171"))
            self._schedule_reconnect()


    def _on_message(self, client, userdata, msg):
        try:
            topic   = msg.topic
            payload = json.loads(msg.payload.decode())
            self.after(0, self._handle_message, topic, payload)
        except Exception as e:
            print(f"Message error: {e}")


    def _feed_reconnect_notice(self):
        t = datetime.now().strftime("%H:%M:%S")
        self._feed_write(f"[{t}] ", "time")
        self._feed_write("🔄 Reconnected to MQTT Broker\n", "reconnect")


    # ── Message handlers ──────────────────────────────
    def _handle_message(self, topic, data):
        t = datetime.now().strftime("%H:%M:%S")

        if topic == "attendance/clock-in":
            self._handle_clock_in(data, t)
        elif topic == "attendance/clock-out":
            self._handle_clock_out(data, t)
        elif topic == "leave/submitted":
            self._handle_leave_submitted(data, t)
        elif topic == "leave/approved":
            self._handle_leave_approved(data, t)
        elif topic == "leave/rejected":
            self._handle_leave_rejected(data, t)
        elif topic == "overtime/confirmed":
            self._handle_overtime(data, t)


    def _handle_clock_in(self, data, t):
        name   = data.get("employee_name", "?")
        no     = data.get("employee_no",   "?")
        dept   = data.get("department",    "?")
        late   = data.get("late_minutes",  0)
        emp_id = data.get("employee_id")
        raw_ci = data.get("clock_in", "")
        ci_str = raw_ci[11:16] if len(raw_ci) >= 16 else raw_ci
        tag    = "late" if late > 0 else "normal"

        # ── Sound ────────────────────────────────────
        self.bell()

        # ── Feed ─────────────────────────────────────
        self._feed_write(f"[{t}] ", "time")
        self._feed_write("🟢 Clock In  ", "clock_in")
        self._feed_write(f"{name} ({no}) {dept}")
        if late > 0:
            self._feed_write(f"  ⚠ Late {late} minutes\n", "late")
        else:
            self._feed_write("  On time\n", "clock_in")

        # ── Toast ────────────────────────────────────
        msg = f"Late {late} minutes" if late > 0 else "On time"
        toast("Clock In", f"{name} ({dept}) {msg}")

        # ── Update dept combo ────────────────────────
        self._update_dept_combo(dept)

        # ── Store data ───────────────────────────────
        self.att_data[emp_id] = {
            "employee_no": no,
            "name":        name,
            "department":  dept,
            "clock_in":    ci_str,
            "clock_out":   "—",
            "worked_hours":"—",
            "status_label": "Late" if late > 0 else "On Time",
            "tag":         tag,
            "late":        late,
        }

        # ── Attendance tree ──────────────────────────
        selected = self.dept_var.get()
        if selected == "All Departments" or selected == dept:
            if emp_id in self.att_rows:
                row_id = self.att_rows[emp_id]
                vals   = list(self.att_tree.item(row_id, "values"))
                vals[3] = ci_str
                self.att_tree.item(row_id, values=vals, tags=(tag,))
            else:
                row_id = self.att_tree.insert(
                    "", 0,
                    values=(no, name, dept, ci_str,
                            "—", "—",
                            "Late" if late > 0 else "On Time"),
                    tags=(tag,)
                )
                self.att_rows[emp_id] = row_id

        # ── Stats ────────────────────────────────────
        n = int(self.stat_vars["clocked_in"].get())
        self.stat_vars["clocked_in"].set(str(n + 1))
        if late > 0:
            l = int(self.stat_vars["late"].get())
            self.stat_vars["late"].set(str(l + 1))


    def _handle_clock_out(self, data, t):
        name   = data.get("employee_name",        "?")
        hours  = data.get("worked_hours",          "?")
        early  = data.get("early_leave_minutes",   0)
        emp_id = data.get("employee_id")
        raw_co = data.get("clock_out", "")
        co_str = raw_co[11:16] if len(raw_co) >= 16 else raw_co

        # ── Sound ────────────────────────────────────
        self.bell()

        # ── Feed ─────────────────────────────────────
        self._feed_write(f"[{t}] ", "time")
        self._feed_write("🔵 Clock Out  ", "clock_out")
        self._feed_write(f"{name}  Hours {hours}h")
        if early > 0:
            self._feed_write(f"  ⚠ Early Leave {early} minutes\n", "late")
        else:
            self._feed_write("\n", "clock_out")

        # ── Toast ────────────────────────────────────
        msg = f"Hours {hours}h Early Leave {early} minutes" if early > 0 \
              else f"Hours {hours}h"
        toast("Clock Out", f"{name} {msg}")

        # ── Update stored data ───────────────────────
        if emp_id in self.att_data:
            self.att_data[emp_id]["clock_out"]    = co_str
            self.att_data[emp_id]["worked_hours"] = f"{hours}h"

        # ── Attendance tree ──────────────────────────
        if emp_id in self.att_rows:
            row_id = self.att_rows[emp_id]
            vals   = list(self.att_tree.item(row_id, "values"))
            vals[4] = co_str
            vals[5] = f"{hours}h"
            self.att_tree.item(row_id, values=vals)


    def _handle_leave_submitted(self, data, t):
        name  = data.get("employee_name", "?")
        ltype = data.get("leave_type",    "?")
        start = data.get("start_date",    "?")
        end   = data.get("end_date",      "?")
        days  = data.get("days",          "?")
        lid   = data.get("leave_id",      "?")
        dept  = data.get("department",    "?")

        # ── Sound + Feed ─────────────────────────────
        self.bell()
        self._feed_write(f"[{t}] ", "time")
        self._feed_write("📋 New Leave Application  ", "leave_submitted")
        self._feed_write(f"{name} applied for {ltype} from {start} to {end}, {days} days\n")

        # ── Toast ────────────────────────────────────
        toast("New Leave Application",
              f"{name}（{dept}）\n{ltype} {start}~{end}")

        # ── Leave tree ───────────────────────────────
        self.leave_tree.insert(
            "", 0,
            values=(lid, name, dept,
                    ltype, start, end,
                    f"{days}days", "pending"),
            tags=("pending",)
        )

        p = int(self.stat_vars["pending"].get())
        self.stat_vars["pending"].set(str(p + 1))


    def _handle_leave_approved(self, data, t):
        name  = data.get("employee_name", "?")
        ltype = data.get("leave_type",    "?")
        by    = data.get("approved_by",   "?")
        lid   = str(data.get("leave_id", ""))

        self._feed_write(f"[{t}] ", "time")
        self._feed_write("✅ Leave Approved  ", "leave_approved")
        self._feed_write(f"{name}'s {ltype} has been approved by {by}\n")

        toast("Leave Approved", f"{name}'s {ltype}\nhas been approved by {by}")
        self._update_leave_row(lid, "Approved", "approved")

        p = int(self.stat_vars["pending"].get())
        self.stat_vars["pending"].set(str(max(0, p - 1)))


    def _handle_leave_rejected(self, data, t):
        name  = data.get("employee_name", "?")
        ltype = data.get("leave_type",    "?")
        note  = data.get("admin_note",    "?")
        lid   = str(data.get("leave_id", ""))

        self._feed_write(f"[{t}] ", "time")
        self._feed_write("❌ Leave Rejected  ", "leave_rejected")
        self._feed_write(f"{name}'s {ltype} has been rejected — {note}\n")

        toast("Leave Rejected", f"{name}'s {ltype}\nReason: {note}")
        self._update_leave_row(lid, "Rejected", "rejected")

        p = int(self.stat_vars["pending"].get())
        self.stat_vars["pending"].set(str(max(0, p - 1)))


    def _handle_overtime(self, data, t):
        name  = data.get("employee_name", "?")
        hours = data.get("hours",          "?")

        self._feed_write(f"[{t}] ", "time")
        self._feed_write("⏰ Overtime Confirmation  ", "overtime")
        self._feed_write(f"{name} {hours} hours of compensatory time has been added to the balance\n")

        toast("Overtime Confirmation", f"{name}\n{hours} hours of compensatory time has been added to the balance")


    # ── System tray ───────────────────────────────────
    def _setup_tray(self):
        icon_img = make_tray_icon()

        menu = pystray.Menu(
            pystray.MenuItem("Show Window",  self._show_from_tray,
                             default=True),
            pystray.MenuItem("Quit App",  self._quit_app),
        )

        self._tray = pystray.Icon(
            "AttendanceMonitor",
            icon_img,
            "Attendance Monitor",
            menu=menu
        )

        tray_thread = threading.Thread(
            target=self._tray.run, daemon=True)
        tray_thread.start()


    def _hide_to_tray(self):
        """Minimise to system tray instead of closing."""
        self.withdraw()


    def _show_from_tray(self, icon=None, item=None):
        """Restore window from tray."""
        self.after(0, self.deiconify)
        self.after(0, self.lift)


    def _quit_app(self, icon=None, item=None):
        """Clean shutdown."""
        self._quit_flag = True
        try:
            self._mqtt.disconnect()
        except Exception:
            pass
        try:
            self._tray.stop()
        except Exception:
            pass
        self.after(0, self.destroy)


    # ── Helpers ───────────────────────────────────────
    def _feed_write(self, text, tag=None):
        self.feed_text.config(state="normal")
        if tag:
            self.feed_text.insert("end", text, tag)
        else:
            self.feed_text.insert("end", text)
        self.feed_text.see("end")
        self.feed_text.config(state="disabled")


    def _update_leave_row(self, leave_id, status_text, tag):
        for row in self.leave_tree.get_children():
            vals = self.leave_tree.item(row, "values")
            if str(vals[0]) == leave_id:
                new_vals    = list(vals)
                new_vals[7] = status_text
                self.leave_tree.item(
                    row, values=new_vals, tags=(tag,))
                break


    def _update_clock(self):
        now = datetime.now().strftime("%Y/%m/%d  %H:%M:%S")
        self.time_label.config(text=now)
        self.after(1000, self._update_clock)


# ── Run ───────────────────────────────────────────────
if __name__ == "__main__":
    app = AttendanceMonitor()
    app.mainloop()