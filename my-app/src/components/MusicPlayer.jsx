import { useEffect, useRef, useState } from "react";

export default function MusicPlayer() {
  const audioRef = useRef(null);

  const [playing, setPlaying] = useState(
    localStorage.getItem("musicPlaying") === "true"
  );

  const [volume, setVolume] = useState(
    Number(localStorage.getItem("volume")) || 0.5
  );

  /* ================= INITIAL LOAD ================= */
  useEffect(() => {
    const audio = audioRef.current;
    if (!audio) return;

    audio.volume = volume;

    if (playing) {
      audio.play().catch(() => {});
    } else {
      audio.pause();
    }
  }, []);

  /* ================= SAVE VOLUME ================= */
  useEffect(() => {
    localStorage.setItem("volume", volume);
    if (audioRef.current) {
      audioRef.current.volume = volume;
    }
  }, [volume]);

  /* ================= SAVE PLAY STATE ================= */
  useEffect(() => {
    localStorage.setItem("musicPlaying", playing.toString());
  }, [playing]);

  /* ================= TOGGLE ================= */
  const toggle = () => {
    const audio = audioRef.current;
    if (!audio) return;

    if (playing) {
      audio.pause();
      setPlaying(false);
    } else {
      audio.play().catch(() => {});
      setPlaying(true);
    }
  };

  return (
    <>
      <audio ref={audioRef} src="/music/song.mp3" loop />

      <div className="music-player">
        <button
          className={`music-btn ${playing ? "active" : ""}`}
          onClick={toggle}
        >
          {playing ? (
            <svg viewBox="0 0 24 24">
              <rect x="5" y="4" width="5" height="16" />
              <rect x="14" y="4" width="5" height="16" />
            </svg>
          ) : (
            <svg viewBox="0 0 24 24">
              <path d="M7 4 L20 12 L7 20 Z" />
            </svg>
          )}
        </button>

        <input
          className="music-slider"
          type="range"
          min="0"
          max="1"
          step="0.01"
          value={volume}
          onChange={(e) => setVolume(Number(e.target.value))}
        />
      </div>
    </>
  );
}