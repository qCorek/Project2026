import { createContext, useContext, useEffect, useState } from "react";

const AuthContext = createContext();
export const useAuth = () => useContext(AuthContext);

const API = "/api";

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [csrfToken, setCsrfToken] = useState(null);
  const [loading, setLoading] = useState(true);

  /* ================= REFRESH SESSION ================= */

  const refreshSession = async () => {
    try {
      const res = await fetch(`${API}/me.php`, {
        credentials: "include",
      });

      if (!res.ok) {
        setUser(null);
        setCsrfToken(null);
        return false;
      }

      const data = await res.json();

      if (data.ok) {
        setUser(data.user);
        setCsrfToken(data.csrf_token);
        return true;
      } else {
        setUser(null);
        setCsrfToken(null);
        return false;
      }
    } catch (err) {
      console.error("Session refresh failed:", err);
      setUser(null);
      setCsrfToken(null);
      return false;
    }
  };

  /* ================= LOGIN ================= */

  const login = async (username, password) => {
    const res = await fetch(`${API}/login.php`, {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ username, password }),
    });

    const data = await res.json();

    if (!data.ok) {
      throw new Error(data.error || "Login failed");
    }

    // IMPORTANT: after login, fetch session + csrf
    await refreshSession();
  };

  /* ================= INIT ================= */

  useEffect(() => {
    const init = async () => {
      await refreshSession();
      setLoading(false);
    };
    init();
  }, []);

  /* ================= LOGOUT ================= */

  const logout = async () => {
    if (!csrfToken) return;

    await fetch(`${API}/logout.php`, {
      method: "POST",
      credentials: "include",
      headers: {
        "X-CSRF-Token": csrfToken,
      },
    });

    setUser(null);
    setCsrfToken(null);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        csrfToken,
        login,
        logout,
        refreshSession,
        loading,
      }}
    >
      {!loading && children}
    </AuthContext.Provider>
  );
}