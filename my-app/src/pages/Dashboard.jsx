import { useState, useEffect } from "react";
import { useAuth } from "../auth/AuthContext";

import Updates from "./Updates";
import MainForm from "./MainForm";
import Users from "./Users";

export default function Dashboard() {
  const { logout, user } = useAuth();

  const [tab, setTab] = useState("updates");
  const [visibleTab, setVisibleTab] = useState(tab);
  const [animating, setAnimating] = useState(false);

  const [discordLink, setDiscordLink] = useState(null);

  useEffect(() => {
    const fetchConfig = async () => {
      try {
        const res = await fetch("/api/site_config.php", { credentials: "include" });
        const data = await res.json();
        if (data.ok && data.config?.discord_invite) setDiscordLink(data.config.discord_invite);
      } catch {}
    };
    fetchConfig();
  }, []);

  useEffect(() => {
    if (tab === visibleTab) return;
    setAnimating(true);
    const t = setTimeout(() => {
      setVisibleTab(tab);
      setAnimating(false);
    }, 160);
    return () => clearTimeout(t);
  }, [tab, visibleTab]);

  const TabButton = ({ id, label, sub }) => (
    <button
      className={`tab-btn ${tab === id ? "active" : ""}`}
      onClick={() => setTab(id)}
      aria-current={tab === id ? "page" : undefined}
      type="button"
    >
      <div className="tab-text">
        <div className="tab-label">{label}</div>
        {sub && <div className="tab-sub">{sub}</div>}
      </div>
    </button>
  );

  return (
    <div className="dash-bg">
      <aside className="sidebar">
        <h3 className="logo">Online .net Obfuscator</h3>

        <nav className="tab-nav" aria-label="Dashboard tabs">
          <TabButton id="updates" label="Updates" sub="Changelog & status" />
          <TabButton id="form" label="Builder" sub="Create a new build" />
          <TabButton id="users" label="Users" sub="Manage accounts" />
        </nav>

        <button
          className="tab-btn"
          disabled={!discordLink}
          onClick={() => discordLink && window.open(discordLink, "_blank")}
          type="button"
        >
          <div className="tab-text">
            <div className="tab-label">Discord</div>
            <div className="tab-sub">{discordLink ? "Open invite" : "Not configured"}</div>
          </div>
        </button>

        <div className="spacer" />

        <div className="sidebar-footer">
          <p className="username">
            {user?.username}
            {user?.role === "admin" && (
              <span className="admin-pill">(admin)</span>
            )}
          </p>

          <button className="logout-btn" onClick={logout} type="button">
            Logout
          </button>
        </div>
      </aside>

      <main className={`content page ${animating ? "fade-out" : "fade-in"}`}>
        {visibleTab === "updates" && <Updates />}
        {visibleTab === "form" && <MainForm />}
        {visibleTab === "users" && <Users />}
      </main>
    </div>
  );
}