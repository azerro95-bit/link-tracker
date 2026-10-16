<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محول الصوت الجزائري الاحترافي - Voice Converter AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between font-sans">

    <!-- Header -->
    <header class="bg-slate-900 border-b border-slate-800 py-4 px-6 shadow-lg">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent">
                <i class="fa-solid fa-microphone-lines ml-2"></i> صوتك الجزائري الذكي
            </h1>
            <span class="text-xs bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-3 py-1 rounded-full font-medium">نظام تحويل PTT المباشر</span>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-xl mx-auto p-6 w-full my-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl relative overflow-hidden">
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl"></div>
            
            <div class="text-center mb-8">
                <h2 class="text-2xl font-extrabold text-white mb-2">سجل صوتك وحوله إلى نبرات مختلفة</h2>
                <p class="text-slate-400 text-sm">يتم تحويل الصوت ليعمل كتسجيل صوتي أصلي (Voice Note) في واتساب وماسنجر.</p>
            </div>

            <div class="space-y-6">
                <!-- قسم التسجيل المباشر -->
                <div class="bg-slate-950/50 border border-slate-800 rounded-xl p-6 text-center">
                    <div id="recordingStatus" class="text-sm font-medium text-slate-400 mb-4">اضغط على الميكروفون للبدء</div>
                    
                    <div class="flex justify-center items-center gap-4">
                        <button id="recordBtn" onclick="toggleRecording()" class="w-16 h-16 bg-rose-600 hover:bg-rose-500 text-white rounded-full flex items-center justify-center shadow-lg transition transform hover:scale-105">
                            <i id="recordIcon" class="fa-solid fa-microphone text-2xl"></i>
                        </button>
                    </div>
                    
                    <div id="audioPreviewContainer" class="hidden mt-4">
                        <p class="text-xs text-emerald-400 mb-2">معاينة الصوت الأصلي:</p>
                        <audio id="audioPreview" controls class="w-full"></audio>
                    </div>
                </div>

                <!-- خيارات النبرة والجنس والعمر -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">اختر النبرة والهوية الصوتية المستهدفة:</label>
                    <select id="voiceModel" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-emerald-500 transition">
                        <option value="dz_female_young">فتاة جزائرية شابة (نبرة طبيعية وناعمة)</option>
                        <option value="dz_female_mature">امرأة جزائرية بالغ (نبرة جادة)</option>
                        <option value="dz_male_old">رجل جزائري كبير في السن (نبرة عميقة)</option>
                        <option value="dz_child">طفل جزائري (صغير السن)</option>
                    </select>
                </div>

                <!-- زر التحويل -->
                <button id="processBtn" onclick="processAudio()" disabled class="w-full bg-gradient-to-r from-emerald-500 to-cyan-500 text-slate-950 font-bold py-3.5 px-6 rounded-xl shadow-lg opacity-50 cursor-not-allowed transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> تحويل إلى تسجيل صوتي حي (Voice Note)
                </button>
            </div>

            <!-- منطقة النتائج والمشاركة -->
            <div id="resultSection" class="hidden mt-8 p-4 bg-slate-950 rounded-xl border border-slate-800">
                <h3 class="text-sm font-semibold text-emerald-400 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-circle-check"></i> تم تحويل الصوت بنجاح بترميز OPUS PTT!
                </h3>
                <audio id="outputAudio" controls class="w-full mb-4"></audio>
                
                <div class="grid grid-cols-2 gap-3">
                    <a id="downloadBtn" href="#" download="voice_note.opus" class="bg-slate-800 hover:bg-slate-700 text-center py-2.5 rounded-lg text-sm font-medium transition text-white">
                        <i class="fa-solid fa-download ml-1"></i> تحميل OPUS
                    </a>
                    <button onclick="shareAudio()" class="bg-emerald-600 hover:bg-emerald-500 text-center py-2.5 rounded-lg text-sm font-medium transition text-white">
                        <i class="fa-brands fa-whatsapp ml-1"></i> مشاركة كسجل صوتي
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="text-center py-4 text-xs text-slate-600 border-t border-slate-900">
        powred by azeez
    </footer>

    <script>
        let mediaRecorder;
        let audioChunks = [];
        let recordedBlob = null;
        let isRecording = false;
        let convertedAudioUrl = "";
        let convertedFile = null;

        async function toggleRecording() {
            const recordBtn = document.getElementById('recordBtn');
            const recordIcon = document.getElementById('recordIcon');
            const recordingStatus = document.getElementById('recordingStatus');
            const audioPreviewContainer = document.getElementById('audioPreviewContainer');
            const processBtn = document.getElementById('processBtn');

            if (!isRecording) {
                try {
                    audioChunks = [];
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    mediaRecorder = new MediaRecorder(stream);
                    
                    mediaRecorder.ondataavailable = event => {
                        audioChunks.push(event.data);
                    };

                    mediaRecorder.onstop = () => {
                        recordedBlob = new Blob(audioChunks, { type: 'audio/webm' });
                        document.getElementById('audioPreview').src = URL.createObjectURL(recordedBlob);
                        audioPreviewContainer.classList.remove('hidden');
                        
                        processBtn.disabled = false;
                        processBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    };

                    mediaRecorder.start();
                    isRecording = true;
                    recordBtn.className = "w-16 h-16 bg-amber-600 hover:bg-amber-500 text-white rounded-full flex items-center justify-center shadow-lg transition transform hover:scale-105 animate-pulse";
                    recordIcon.className = "fa-solid fa-stop text-2xl";
                    recordingStatus.textContent = "جاري التسجيل... انقر مرة أخرى للإيقاف";
                    audioPreviewContainer.classList.add('hidden');
                    processBtn.disabled = true;
                    processBtn.classList.add('opacity-50', 'cursor-not-allowed');

                } catch (err) {
                    alert('تعذر الوصول إلى الميكروفون.');
                }
            } else {
                mediaRecorder.stop();
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
                isRecording = false;
                recordBtn.className = "w-16 h-16 bg-rose-600 hover:bg-rose-500 text-white rounded-full flex items-center justify-center shadow-lg transition transform hover:scale-105";
                recordIcon.className = "fa-solid fa-microphone text-2xl";
                recordingStatus.textContent = "تم التسجيل! اضغط للتحويل أدناه.";
            }
        }

        async function processAudio() {
            if (!recordedBlob) return;

            const processBtn = document.getElementById('processBtn');
            const modelSelect = document.getElementById('voiceModel').value;
            
            processBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري المعالجة بالذكاء الاصطناعي...';
            processBtn.disabled = true;

            const formData = new FormData();
            formData.append('audio', recordedBlob, 'recording.webm');
            formData.append('model', modelSelect);

            try {
                const response = await fetch('process.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    convertedAudioUrl = data.file_url;
                    
                    // إحضار الملف كـ Blob للمشاركة المباشرة عبر Web Share API
                    const audioResponse = await fetch(convertedAudioUrl);
                    const audioBlob = await audioResponse.blob();
                    convertedFile = new File([audioBlob], "voice_note.opus", { type: "audio/ogg; codecs=opus" });

                    document.getElementById('resultSection').classList.remove('hidden');
                    document.getElementById('outputAudio').src = convertedAudioUrl;
                    document.getElementById('downloadBtn').href = convertedAudioUrl;
                } else {
                    alert('حدث خطأ أثناء المعالجة: ' + data.error);
                }
            } catch (err) {
                alert('فشل الاتصال بالخادم.');
                console.error(err);
            } finally {
                processBtn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> تحويل إلى تسجيل صوتي حي (Voice Note)';
                processBtn.disabled = false;
            }
        }

        async function shareAudio() {
            if (convertedFile && navigator.canShare && navigator.canShare({ files: [convertedFile] })) {
                try {
                    await navigator.share({
                        files: [convertedFile],
                        title: 'تسجيل صوتي',
                        text: 'استمع للتسجيل الصوتي:'
                    });
                } catch (err) {
                    console.log('تم إلغاء المشاركة أو غير مدعومة.');
                }
            } else {
                alert('قم بتحميل ملف OPUS وإرساله في واتساب/ماسنجر كـ Document/Audio وسيتعامل معه كـ Voice Note.');
            }
        }
    </script>
</body>
</html>
