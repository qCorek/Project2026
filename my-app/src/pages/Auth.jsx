import { useState } from "react";
import { useAuth } from "../auth/AuthContext";
import AnimatedHeadline from "./AnimatedHeadline";
import { useNavigate, useLocation } from "react-router-dom";

const API = "/api";

export default function Auth() {
  const { login, refreshSession } = useAuth();

  const navigate = useNavigate();
  const location = useLocation();

  const [tab, setTab] = useState("login");
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const submit = async (e) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      if (tab === "login") {
        await login(username, password);
      } else {
        const res = await fetch(`${API}/register.php`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          credentials: "include",
          body: JSON.stringify({
            username,
            password,
          }),
        });

        const data = await res.json();

        if (!data.ok) {
          throw new Error(data.error || "Registration failed");
        }

        await refreshSession();
      }

      const from = location.state?.from || "/dashboard";
      navigate(from, { replace: true });
    } catch (err) {
      setError(err.message || "Something went wrong");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-bg">
      <div className="auth-wrapper">
        {/* HEADER */}
        <div className="product-header">
          <h1 className="product-title">Online .NET Obfuscator</h1>
          <span className="product-sub">Minimal • Secure • Professional</span>
        </div>

        <AnimatedHeadline />

        {/* CARD */}
        <div className="auth-card">
          {/* TABS */}
          <div className="tabs">
            <button
              type="button"
              className={tab === "login" ? "active" : ""}
              onClick={() => {
                setTab("login");
                setError("");
              }}
            >
              Login
            </button>

            <button
              type="button"
              className={tab === "register" ? "active" : ""}
              onClick={() => {
                setTab("register");
                setError("");
              }}
            >
              Register
            </button>
          </div>

          {/* FORM */}
          <form className="form" onSubmit={submit}>
            <div className="form-header">
              <h2 className="form-title">
                {tab === "login" ? "Welcome back" : "Create your account"}
              </h2>

              <p className="form-sub">
                {tab === "login"
                  ? "Sign in to continue building"
                  : "Join and start building securely"}
              </p>
            </div>

            <div className="input-group">
              <input
                required
                placeholder=" "
                value={username}
                onChange={(e) => setUsername(e.target.value)}
              />
              <label>Username</label>
            </div>

            <div className="input-group">
              <input
                type="password"
                required
                placeholder=" "
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
              <label>Password</label>
            </div>

            {error && <p className="error-text">{error}</p>}

            <button className="primary-btn" disabled={loading}>
              {loading && <span className="btn-spinner" />}
              {loading
                ? "Please wait..."
                : tab === "login"
                ? "Sign In"
                : "Sign Up"}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}