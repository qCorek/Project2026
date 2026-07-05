import { useEffect, useState } from "react";

export default function Updates() {
  const [updates, setUpdates] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const loadUpdates = async () => {
      try {
        const res = await fetch("/api/updates.php", {
          credentials: "include",
        });

        const data = await res.json();

        if (!data.ok) {
          throw new Error(data.error || "Failed to load updates");
        }

        setUpdates(data.updates || []);
      } catch (err) {
        setError(err.message || "Failed to load updates");
      } finally {
        setLoading(false);
      }
    };

    loadUpdates();
  }, []);

  if (loading) {
    return <div className="updates-wrapper">Loading updates...</div>;
  }

  if (error) {
    return <div className="updates-wrapper error-text">{error}</div>;
  }

  return (
    <div className="updates-wrapper">
      {updates.map((u) => (
        <div key={u.version} className="update-block card">
          <div className="update-header">
            <span className="update-version">v{u.version}</span>
            <span className="update-label">Update</span>
          </div>

          <ul className="update-list">
            {u.changes.map((c, i) => (
              <li key={i}>• {c}</li>
            ))}
          </ul>
        </div>
      ))}
    </div>
  );
}