import { useState, useRef } from "react";
import { useAuth } from "../auth/AuthContext";

export default function MainForm() {
  const [file, setFile] = useState(null);

  const [renaming, setRenaming] = useState(true);
  const [stringEncryption, setStringEncryption] = useState(true);
  const [controlFlow, setControlFlow] = useState(true);
  const [resourceProtection, setResourceProtection] = useState(false);
  const [referenceProxy, setReferenceProxy] = useState(false);
  const [antiTamper, setAntiTamper] = useState(false);

  const [logs, setLogs] = useState([]);
  const [compiling, setCompiling] = useState(false);

  const { user, csrfToken, refreshSession } = useAuth();
  const inputRef = useRef(null);

  const addLog = (msg, type = "info") =>
    setLogs((p) => [
      ...p,
      { text: `[${new Date().toLocaleTimeString()}] ${msg}`, type },
    ]);

  const getFileExtension = (name) => {
    const lower = name.toLowerCase();

    if (lower.endsWith(".dll")) return "dll";
    if (lower.endsWith(".exe")) return "exe";

    return null;
  };

  const getBaseName = (name) => {
    return name.replace(/\.[^/.]+$/, "") || "protected";
  };

  const handleFile = (f) => {
    if (!f) return;

    const ext = getFileExtension(f.name);

    if (!ext) {
      inputRef.current.value = "";
      return addLog("Invalid file. Please select a .exe or .dll", "error");
    }

    setFile(f);
    addLog(`Loaded ${f.name}`, "success");
  };

  const selectedOptionsCount = [
    renaming,
    stringEncryption,
    controlFlow,
    resourceProtection,
    referenceProxy,
    antiTamper,
  ].filter(Boolean).length;

  const getDownloadName = (res) => {
    const contentDisposition = res.headers.get("content-disposition") || "";

    const match = contentDisposition.match(/filename="?([^"]+)"?/i);

    if (match?.[1]) {
      return match[1];
    }

    const ext = file ? getFileExtension(file.name) : "exe";
    const base = file ? getBaseName(file.name) : "protected";

    return `${base}-protected.${ext || "exe"}`;
  };

  const parseErrorResponse = async (res) => {
    const contentType = res.headers.get("content-type") || "";

    if (contentType.includes("application/json")) {
      try {
        const data = await res.json();
        return data?.error || data?.message || "Build failed";
      } catch {
        return "Build failed";
      }
    }

    try {
      const text = await res.text();
      return text || "Build failed";
    } catch {
      return "Build failed";
    }
  };

  const compile = async () => {
    if (!file) return addLog("Select a file first", "error");

    if (selectedOptionsCount <= 0) {
      return addLog("Select at least one protection option", "error");
    }

    if (!csrfToken) {
      console.warn("CSRF token missing at compile time");
      return addLog("Session still initializing. Try again.", "error");
    }

    setCompiling(true);
    addLog("Starting obfuscation...", "info");

    try {
      const form = new FormData();

      form.append("file", file);

      // ConfuserEx-compatible frontend option names
      form.append("renaming", renaming ? "1" : "0");
      form.append("stringEncryption", stringEncryption ? "1" : "0");
      form.append("controlFlow", controlFlow ? "1" : "0");
      form.append("resourceProtection", resourceProtection ? "1" : "0");
      form.append("referenceProxy", referenceProxy ? "1" : "0");
      form.append("antiTamper", antiTamper ? "1" : "0");

      form.append("originalName", file.name);
      form.append("outputExtension", getFileExtension(file.name));

      const res = await fetch("/api/compile.php", {
        method: "POST",
        credentials: "include",
        headers: {
          "X-CSRF-Token": csrfToken,
        },
        body: form,
      });

      if (!res.ok) {
        const message = await parseErrorResponse(res);
        throw new Error(message);
      }

      const contentType = res.headers.get("content-type") || "";

      if (contentType.includes("application/json")) {
        const data = await res.json();

        if (data?.error) {
          throw new Error(data.error);
        }

        throw new Error("Backend returned JSON instead of a protected file");
      }

      const blob = await res.blob();

      if (!blob || blob.size === 0) {
        throw new Error("Backend returned an empty file");
      }

      const url = URL.createObjectURL(blob);

      const a = document.createElement("a");
      a.href = url;
      a.download = getDownloadName(res);
      a.click();

      URL.revokeObjectURL(url);

      addLog("✔ Obfuscation finished", "success");

      await refreshSession();
    } catch (err) {
      addLog(err.message || "Build failed", "error");
    } finally {
      setCompiling(false);
    }
  };

  const OptionSwitch = ({ label, checked, onChange }) => (
    <label className="switch-row">
      <span>{label}</span>
      <input
        type="checkbox"
        checked={checked}
        disabled={compiling}
        onChange={onChange}
      />
      <span className="switch" />
    </label>
  );

  return (
    <div className="builder-card">
      <div className="builder-header">
        <div>
          <h2>Builder</h2>
          <span className="builder-sub">Obfuscate & protect .NET binaries</span>
        </div>

        <div
          className={`balance-badge ${
            user?.role === "admin"
              ? "admin"
              : (user?.credits ?? 0) <= 0
              ? "low"
              : ""
          }`}
        >
          💰{" "}
          {user?.role === "admin"
            ? "Unlimited"
            : `${user?.credits ?? 0} Credits`}
        </div>
      </div>

      {/* INPUT */}
      <div className="builder-section">
        <h4>Input</h4>

        <div
          className="drop-zone"
          onClick={() => {
            if (!compiling) inputRef.current?.click();
          }}
          onDragOver={(e) => e.preventDefault()}
          onDrop={(e) => {
            e.preventDefault();
            if (!compiling) handleFile(e.dataTransfer.files[0]);
          }}
        >
          {file
            ? `📦 ${file.name}`
            : "Drag & drop .exe/.dll or click to select"}

          <input
            hidden
            ref={inputRef}
            type="file"
            accept=".exe,.dll"
            disabled={compiling}
            onChange={(e) => handleFile(e.target.files[0])}
          />
        </div>
      </div>

      {/* SETTINGS */}
      <div className="builder-section">
        <h4>Protection Options</h4>

        <div className="option-list">
          <OptionSwitch
            label="Symbol Renaming"
            checked={renaming}
            onChange={() => setRenaming((v) => !v)}
          />

          <OptionSwitch
            label="String Encryption"
            checked={stringEncryption}
            onChange={() => setStringEncryption((v) => !v)}
          />

          <OptionSwitch
            label="Control Flow"
            checked={controlFlow}
            onChange={() => setControlFlow((v) => !v)}
          />

          <OptionSwitch
            label="Resource Protection"
            checked={resourceProtection}
            onChange={() => setResourceProtection((v) => !v)}
          />

          <OptionSwitch
            label="Reference Proxy"
            checked={referenceProxy}
            onChange={() => setReferenceProxy((v) => !v)}
          />

          <OptionSwitch
            label="Anti-Tamper"
            checked={antiTamper}
            onChange={() => setAntiTamper((v) => !v)}
          />
        </div>
      </div>

      {/* BUILD */}
      <div className="builder-section">
        <h4>Build</h4>

        <div className="compile-row">
          <div className="compile-left">
            <span className="builder-sub">
              {file
                ? `${selectedOptionsCount} protections selected`
                : "No file selected"}
            </span>
          </div>

          <button
            className="compile-btn"
            disabled={
              compiling ||
              !file ||
              selectedOptionsCount <= 0 ||
              (user?.role !== "admin" && user?.credits <= 0)
            }
            onClick={compile}
          >
            {compiling
              ? "Obfuscating..."
              : user?.role !== "admin" && user?.credits <= 0
              ? "No Credits"
              : !file
              ? "Select File"
              : selectedOptionsCount <= 0
              ? "Select Options"
              : "Obfuscate"}
          </button>
        </div>
      </div>

      {/* CONSOLE */}
      <div className="builder-console">
        {logs.length === 0 ? (
          <div className="log info">Waiting for input...</div>
        ) : (
          logs.map((l, i) => (
            <div key={i} className={`log ${l.type}`}>
              {l.text}
            </div>
          ))
        )}
      </div>
    </div>
  );
}