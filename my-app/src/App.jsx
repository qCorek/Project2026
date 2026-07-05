import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import MusicPlayer from "./components/MusicPlayer";

import Auth from "./pages/Auth";
import Dashboard from "./pages/Dashboard";
import RequireAuth from "./auth/RequireAuth";
import { AuthProvider } from "./auth/AuthContext";

import Updates from "./pages/Updates";
import MainForm from "./pages/MainForm";
import Users from "./pages/Users";

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <MusicPlayer />

        <Routes>
          {/* redirect root */}
          <Route path="/" element={<Navigate to="/auth" replace />} />

          {/* auth */}
          <Route path="/auth" element={<Auth />} />

          {/* protected dashboard layout */}
          <Route
            path="/dashboard"
            element={
              <RequireAuth>
                <Dashboard />
              </RequireAuth>
            }
          >
            {/* nested routes */}
            <Route index element={<Navigate to="updates" replace />} />
            <Route path="updates" element={<Updates />} />
            <Route path="form" element={<MainForm />} />
            <Route path="users" element={<Users />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}
