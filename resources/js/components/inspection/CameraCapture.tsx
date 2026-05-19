import { useEffect, useRef, useState } from 'react';
import { Camera, X, RotateCcw, CheckCircle2 } from 'lucide-react';
import Button from '@/components/ui/Button';

interface Props {
    onCapture: (file: File) => void;
    existingPhotoUrl?: string | null;
    existingPhotoFilename?: string | null;
    error?: string | null;
}

export default function CameraCapture({ onCapture, existingPhotoUrl, existingPhotoFilename, error }: Props) {
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const [active, setActive] = useState(false);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [facingMode, setFacingMode] = useState<'environment' | 'user'>('environment');

    const stopStream = () => {
        if (streamRef.current) {
            streamRef.current.getTracks().forEach((t) => t.stop());
            streamRef.current = null;
        }
        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }
    };

    const start = async () => {
        setCameraError(null);
        if (!navigator.mediaDevices?.getUserMedia) {
            setCameraError("Caméra non supportée par ce navigateur.");
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: facingMode }, width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false,
            });
            streamRef.current = stream;
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                await videoRef.current.play();
            }
            setActive(true);
        } catch (e: any) {
            setCameraError(
                e?.name === 'NotAllowedError'
                    ? "Accès à la caméra refusé. Autorisez la caméra dans votre navigateur."
                    : `Impossible d'ouvrir la caméra : ${e?.message ?? 'erreur inconnue'}`
            );
        }
    };

    const cancel = () => {
        stopStream();
        setActive(false);
    };

    const switchCamera = async () => {
        const next = facingMode === 'environment' ? 'user' : 'environment';
        setFacingMode(next);
        stopStream();
        // start() will pick up the new facingMode via state on next call
        setTimeout(() => { if (active) start(); }, 50);
    };

    const snap = () => {
        const video = videoRef.current;
        if (!video) return;

        const w = video.videoWidth;
        const h = video.videoHeight;
        if (!w || !h) {
            setCameraError("Flux vidéo non prêt — patientez une seconde.");
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        ctx.drawImage(video, 0, 0, w, h);

        canvas.toBlob((blob) => {
            if (!blob) return;
            const ts = new Date().toISOString().replace(/[:.]/g, '-');
            const file = new File([blob], `vehicle-${ts}.jpg`, { type: 'image/jpeg' });
            const url = URL.createObjectURL(blob);
            setPreviewUrl((prev) => { if (prev) URL.revokeObjectURL(prev); return url; });
            onCapture(file);
            stopStream();
            setActive(false);
        }, 'image/jpeg', 0.85);
    };

    useEffect(() => stopStream, []);
    useEffect(() => () => { if (previewUrl) URL.revokeObjectURL(previewUrl); }, [previewUrl]);

    return (
        <div>
            {!active && (
                <div className="space-y-2">
                    {(previewUrl || existingPhotoUrl) && (
                        <div className="flex items-start gap-3">
                            <img
                                src={previewUrl ?? existingPhotoUrl!}
                                alt="Aperçu véhicule"
                                className="max-h-48 rounded border border-[var(--color-border)]"
                            />
                            <div className="text-sm">
                                {previewUrl ? (
                                    <span className="flex items-center gap-1 text-emerald-600"><CheckCircle2 size={14} /> Nouvelle photo capturée</span>
                                ) : (
                                    <span className="text-[var(--color-text-muted)]">Photo actuelle : {existingPhotoFilename ?? '—'}</span>
                                )}
                            </div>
                        </div>
                    )}
                    <Button type="button" variant="secondary" onClick={start}>
                        <Camera size={16} className="mr-2" />
                        {previewUrl || existingPhotoUrl ? "Reprendre la photo" : "Ouvrir la caméra"}
                    </Button>
                    {(cameraError || error) && (
                        <p className="text-xs text-[var(--color-danger)] mt-1">{cameraError ?? error}</p>
                    )}
                    <p className="text-xs text-[var(--color-text-muted)]">
                        La photo doit être prise en direct sur le véhicule (pas d'upload depuis la galerie).
                    </p>
                </div>
            )}

            {active && (
                <div className="space-y-2">
                    <div className="relative bg-black rounded-lg overflow-hidden" style={{ maxWidth: 640 }}>
                        <video ref={videoRef} playsInline muted className="w-full h-auto" />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" onClick={snap}>
                            <Camera size={16} className="mr-2" /> Capturer
                        </Button>
                        <Button type="button" variant="secondary" onClick={switchCamera}>
                            <RotateCcw size={16} className="mr-2" /> Changer caméra
                        </Button>
                        <Button type="button" variant="secondary" onClick={cancel}>
                            <X size={16} className="mr-2" /> Annuler
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
