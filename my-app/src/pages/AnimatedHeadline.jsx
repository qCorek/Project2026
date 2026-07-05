import { useEffect, useState } from "react";

const words = [
  "Fast builds",
  "Secure runtime",
  "Minimal footprint",
  "Private by default",
  "Built for professionals"
];

export default function AnimatedHeadline() {
  const [index, setIndex] = useState(0);
  const [show, setShow] = useState(true);

  useEffect(() => {
    const interval = setInterval(() => {
      setShow(false);

      setTimeout(() => {
        setIndex((i) => (i + 1) % words.length);
        setShow(true);
      }, 300);
    }, 2000);

    return () => clearInterval(interval);
  }, []);

  return (
<h2 className={`headline ${show ? "show" : ""}`}>
      {words[index]}
    </h2>
  );
}
