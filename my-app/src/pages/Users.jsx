import { useEffect, useState } from "react";
import { useAuth } from "../auth/AuthContext";

const API = "/api";

export default function Users() {
  const { user, csrfToken } = useAuth();

  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [maintenance, setMaintenance] = useState(false);

  const loadUsers = async () => {
    try {
      const res = await fetch(`${API}/users.php`, { credentials: "include" });
      if (!res.ok) throw new Error("Failed to load users");

      const data = await res.json();
      if (!data.ok) throw new Error("Unauthorized");

      setUsers(data.users);
    } catch (err) {
      setError(err.message);
    }
    setLoading(false);
  };

  const loadMaintenance = async () => {
    try {
      const res = await fetch(`${API}/maintenance_status.php`, {
        credentials: "include",
      });
      const data = await res.json();
      setMaintenance(data.enabled);
    } catch {}
  };

  useEffect(() => {
    loadUsers();
    if (user?.role === "admin") loadMaintenance();
  }, []);

  const toggleBan = async (id, banned) => {
    if (!csrfToken) return alert("CSRF token missing. Refresh the page.");

    const res = await fetch(`${API}/${banned ? "unban_user.php" : "ban_user.php"}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      credentials: "include",
      body: JSON.stringify({ user_id: id }),
    });

    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.ok) return alert(data?.error || "Request failed");

    setUsers((prev) =>
      prev.map((u) => (u.id === id ? { ...u, is_banned: !banned } : u))
    );
  };

  const updateCredits = async (id, newCredits) => {
    if (newCredits < 0) return;
    if (!csrfToken) return alert("CSRF token missing. Refresh the page.");

    // optimistic update
    setUsers((prev) =>
      prev.map((u) => (u.id === id ? { ...u, credits: newCredits } : u))
    );

    const res = await fetch(`${API}/update_credits.php`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      credentials: "include",
      body: JSON.stringify({ user_id: id, credits: newCredits }),
    });

    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.ok) {
      alert(data?.error || "Request failed");
      loadUsers(); // revert to server truth if it failed
    }
  };

  const toggleMaintenance = async () => {
    if (!csrfToken) return alert("CSRF token missing. Refresh the page.");

    try {
      const res = await fetch(`${API}/toggle_maintenance.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken,
        },
        credentials: "include",
        body: JSON.stringify({ enabled: !maintenance }),
      });

      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data?.error || "Failed");

      setMaintenance(data.enabled);
    } catch (e) {
      alert(e.message || "Failed to toggle maintenance");
    }
  };

  return (
    <div className="users-card card page-animate">
      <div className="users-top">
        <h2>User List</h2>
        <span className="count-badge">
          {users.length} User{users.length !== 1 && "s"}
        </span>
      </div>

      {user?.role === "admin" && (
        <div style={{ marginBottom: 16 }}>
          <button
            className="secondary-btn"
            onClick={toggleMaintenance}
            disabled={!csrfToken}
            title={!csrfToken ? "Loading security token..." : ""}
          >
            {maintenance ? "Disable Maintenance" : "Enable Maintenance"}
          </button>
        </div>
      )}

      {loading && <p className="muted">Loading users...</p>}
      {error && <p className="error">{error}</p>}

      {!loading && !error && users.length > 0 && (
        <table className="users-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Username</th>
              <th>Credits</th>
              <th>Created</th>
              {user?.role === "admin" && <th>Actions</th>}
            </tr>
          </thead>

          <tbody>
            {users.map((u, i) => (
              <tr key={u.id} style={u.is_banned ? { opacity: 0.5 } : {}}>
                <td className="index">{i + 1}</td>
                <td>
                  <span className="username">{u.username}</span>
                  {u.role === "admin" && <span className="role-badge">Admin</span>}
                  {u.is_banned && (
                    <span style={{
                      marginLeft: 8, fontSize: 12, padding: "2px 6px",
                      borderRadius: 6, background: "#ef4444", color: "white"
                    }}>
                      Banned
                    </span>
                  )}
                </td>

                <td>
                  {user?.role === "admin" ? (
                    <input
                      type="number"
                      min="0"
                      value={u.credits}
                      style={{ width: 80 }}
                      disabled={!csrfToken}
                      onChange={(e) =>
                        updateCredits(u.id, parseInt(e.target.value, 10) || 0)
                      }
                    />
                  ) : (
                    <span>{u.credits}</span>
                  )}
                </td>

                <td className="date">{new Date(u.created_at).toLocaleString()}</td>

                {user?.role === "admin" && (
                  <td>
                    <button
                      className="secondary-btn"
                      onClick={() => toggleBan(u.id, u.is_banned)}
                      disabled={!csrfToken}
                      title={!csrfToken ? "Loading security token..." : ""}
                    >
                      {u.is_banned ? "Unban" : "Ban"}
                    </button>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}