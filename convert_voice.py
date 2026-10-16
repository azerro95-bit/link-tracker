import sys
import os
import subprocess
import numpy as np
import soundfile as sf
import librosa

def process_audio(input_path, output_path, model_type):
    temp_wav = input_path + "_temp.wav"
    processed_wav = input_path + "_proc.wav"

    try:
        # Step 1: تحويل الصوت المدخل لـ WAV بواسطة FFmpeg
        subprocess.run([
            "ffmpeg", "-y", "-i", input_path,
            "-ar", "22050", "-ac", "1", temp_wav
        ], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        # Step 2: تحميل الصوت ومعالجة الترددات (Pitch Shift / Formant Adjustment)
        y, sr = librosa.load(temp_wav, sr=22050)

        # تحديد التردد والنبرة بحسب النموذج المختار
        if model_type == "dz_female_young":
            n_steps = 4.5  # رفع التردد لتصبح نبرة فتاة شابة
        elif model_type == "dz_female_mature":
            n_steps = 2.5  # رفع التردد بنسبة أقل لنبرة امرأة بالغة
        elif model_type == "dz_male_old":
            n_steps = -3.5 # خفض التردد لنبرة رجل مسن عميقة
        elif model_type == "dz_child":
            n_steps = 6.0  # رفع التردد بنسبة عالية لصوت طفل
        else:
            n_steps = 0.0

        # تطبيق تغيير التردد
        if n_steps != 0.0:
            y_shifted = librosa.effects.pitch_shift(y=y, sr=sr, n_steps=n_steps)
        else:
            y_shifted = y

        sf.write(processed_wav, y_shifted, sr)

        # Step 3: ضغط وتحويل الصوت إلى صيغة OGG/OPUS بترميز PTT الرسمي لواتساب وماسنجر
        subprocess.run([
            "ffmpeg", "-y", "-i", processed_wav,
            "-c:a", "libopus",
            "-b:a", "24k",
            "-vbr", "on",
            "-compression_level", "10",
            "-frame_duration", "60",
            "-application", "voip",
            output_path
        ], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        return True

    except Exception as e:
        print(f"Error processing audio: {str(e)}", file=sys.stderr)
        return False
    finally:
        # التنظيف وإزالة الملفات المؤقتة
        if os.path.exists(temp_wav):
            os.remove(temp_wav)
        if os.path.exists(processed_wav):
            os.remove(processed_wav)

if __name__ == "__main__":
    if len(sys.argv) < 4:
        sys.exit(1)

    input_file = sys.argv[1]
    output_file = sys.argv[2]
    model = sys.argv[3]

    success = process_audio(input_file, output_file, model)
    if not success:
        sys.exit(1)
