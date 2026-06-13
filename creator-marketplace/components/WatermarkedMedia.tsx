// Visual overlay applied to creator demo content (videos, photos) before it's
// shown publicly to clients. Real signing happens server-side (ffmpeg/sharp);
// this component is the visible UX so creators see exactly how their demos
// will be presented.

export function WatermarkedMedia({
  src,
  alt,
  type = 'image',
  className,
}: {
  src: string;
  alt: string;
  type?: 'image' | 'video';
  className?: string;
}) {
  return (
    <div className={`relative overflow-hidden ${className ?? ''}`}>
      {type === 'video' ? (
        // eslint-disable-next-line jsx-a11y/media-has-caption
        <video src={src} className="h-full w-full object-cover" muted loop />
      ) : (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={src} alt={alt} className="h-full w-full object-cover" />
      )}
      {/* Diagonal repeating watermark — protects demo content from being grabbed */}
      <div
        className="absolute inset-0 pointer-events-none flex items-center justify-center select-none"
        aria-hidden
        style={{
          backgroundImage:
            "repeating-linear-gradient(-30deg, rgba(255,255,255,0.0) 0 90px, rgba(124,58,237,0.10) 90px 92px)",
        }}
      >
        <span className="absolute top-2 left-2 text-[10px] font-bold text-white/90 bg-brand-700/60 backdrop-blur px-1.5 py-0.5 rounded">
          კრეატორები.ge
        </span>
        <span className="text-white/40 text-2xl sm:text-3xl font-extrabold tracking-widest rotate-[-30deg]">
          PREVIEW · კრეატორები.ge
        </span>
        <span className="absolute bottom-2 right-2 text-[10px] font-mono text-white/80 bg-black/40 px-1.5 py-0.5 rounded">
          © {new Date().getFullYear()}
        </span>
      </div>
    </div>
  );
}
