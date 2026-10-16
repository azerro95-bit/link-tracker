<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محول الصوت الجزائري الاحترافي بالذكاء الاصطناعي</title>
    <!-- Tailwind CSS للتصميم الجبار -->
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
            <span class="text-xs bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-3 py-1 rounded-full font-medium">النسخة الاحترافية</span>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-xl mx-auto p-6 w-full my-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl relative overflow-hidden">
            <!-- تأثير إضاءة خلفية خفيف -->
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl"></div>
            
            <div class="text-center mb-8">
                <h2 class="text-2xl font-extrabold text-white mb-2">سجل صوتك مباشرة وحوله لنبرة جزائرية طبيعية</h2>
                <p class="text-slate-400 text-sm">تكلم بالميكروفون مباشرة، ودع الذكاء الاصطناعي يقوم بالباقي.</p>
            </div>

            <!-- قسم التسجيل المباشر -->
            <div class="space-y-6">
                <!-- أزرار التحكم بالتسجيل -->
                <div class="bg-slate-950/50 border border-slate-800 rounded-xl p-6 text-center">
                    <div id="recordingStatus" class="text-sm font-medium text-slate-400 mb-4">اضغط على زر التسجيل وابدأ الكلام</div>
                    
                    <div class="flex justify-center items-center gap-4">
                        <button id="recordBtn" onclick="toggleRecording()" class="w-16 h-16 bg-rose-600 hover:bg-rose-500 text-white rounded-full flex items-center justify-center shadow-lg transition transform hover:scale-105">
                            <i id="recordIcon" class="fa-solid fa-microphone text-2xl"></i>
                        </button>
                    </div>
                    
                    <!-- مشغل الصوت بعد التسجيل -->
                    <div id="audioPreviewContainer" class="hidden mt-4">
                        <p class="text-xs text-emerald-400 mb-2">استمع لصوتك قبل الإرسال:</p>
                        <audio id="audioPreview" controls class="w-full"></audio>
                    </div>
                </div>

                <!-- خيارات التحويل -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">اختر النبرة المستهدفة:</label>
                    <select id="voiceModel" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-emerald-500 transition">
                        <option value="algerian_girl_1">صوت فتاة جزائرية (ناعم وطبيعي)</option>
                        <option value="algerian_girl_2">صوت فتاة جزائرية (حماسي)</option>
                    </select>
                </div>

                <!-- زر الإرسال والمعالجة -->
                <button id="processBtn" onclick="processAudio()" disabled class="w-full bg-gradient-to-r from-emerald-500 to-cyan-500 text-slate-950 font-bold py-3.5 px-6 rounded-xl shadow-lg opacity-55 cursor-not-allowed transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> معالجة وتغيير الصوت الآن
                </button>
            </div>

            <!-- منطقة النتيجة والتحميل / المشاركة -->
            <div id="resultSection" class="hidden mt-8 p-4 bg-slate-950 rounded-xl border border-slate-800">
                <h3 class="text-sm font-semibold text-emerald-400 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-circle-check"></i> تم تحويل الصوت بنجاح!
                </h3>
                <audio id="outputAudio" controls class="w-full mb-4"></audio>
                <div class="flex gap-3">
                    <a id="downloadBtn" href="#" class="flex-1 bg-slate-800 hover:bg-slate-700 text-center py-2.5 rounded-lg text-sm font-medium transition text-white">
                        <i class="fa-solid fa-download ml-1"></i> تحميل المقطع
                    </a>
                    <button onclick="shareToMessenger()" class="flex-1 bg-blue-600 hover:bg-blue-500 text-center py-2.5 rounded-lg text-sm font-medium transition text-white">
                        <i class="fa-brands fa-facebook-messenger ml-1"></i> إرسال لماسنجر
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="text-center py-4 text-xs text-slate-600 border-t border-slate-900">
        powred by azeez
    </footer>

    <!-- أكواد جافاسكريبت للتحكم بالميكروفون والتسجيل -->
    <script>
        let mediaRecorder;
        let audioChunks = [];
        let recordedBlob = null;
        let isRecording = false;

        async function toggleRecording() {
            const recordBtn = document.getElementById('recordBtn');
            const recordIcon = document.getElementById('recordIcon');
            const recordingStatus = document.getElementById('recordingStatus');
            const audioPreviewContainer = document.getElementById('audioPreviewContainer');
            const processBtn = document.getElementById('processBtn');

            if (!isRecording) {
                // بدء التسجيل
                try {
                    audioChunks = [];
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    mediaRecorder = new MediaRecorder(stream);
                    
                    mediaRecorder.ondataavailable = event => {
                        audioChunks.push(event.data);
                    };

                    mediaRecorder.onstop = () => {
                        recordedBlob = new Blob(audioChunks, { type: 'audio/webm' });
                        const audioUrl = URL.createObjectURL(recordedBlob);
                        const audioPreview = document.getElementById('audioPreview');
                        audioPreview.src = audioUrl;
                        audioPreviewContainer.classList.remove('hidden');
                        
                        // تفعيل زر المعالجة
                        processBtn.disabled = false;
                        processBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    };

                    mediaRecorder.start();
                    isRecording = true;
                    recordBtn.classList.remove('bg-rose-600', 'hover:bg-rose-500');
                    recordBtn.classList.add('bg-amber-600', 'hover:bg-amber-500', 'animate-pulse');
                    recordIcon.className = "fa-solid fa-stop text-2xl";
                    recordingStatus.textContent = "جاري التسجيل... انقر للإيقاف";
                    audioPreviewContainer.classList.add('hidden');
                    processBtn.disabled = true;
                    processBtn.classList.add('opacity-50', 'cursor-not-allowed');

                } catch (err) {
                    alert('تعذر الوصول إلى الميكروفون. يرجى السماح للمتصفح بالاستخدام.');
                    console.error(err);
                }
            } else {
                // إيقاف التسجيل
                mediaRecorder.stop();
                // إيقاف استخدام الميكروفون الفعلي
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
                
                isRecording = false;
                recordBtn.classList.remove('bg-amber-600', 'hover:bg-amber-500', 'animate-pulse');
                recordBtn.classList.add('bg-rose-600', 'hover:bg-rose-500');
                recordIcon.className = "fa-solid fa-microphone text-2xl";
                recordingStatus.textContent = "تم التسجيل بنجاح! يمكنك الاستماع أو معالجة الصوت";
            }
        }

        function processAudio() {
            if (!recordedBlob) return;

            const processBtn = document.getElementById('processBtn');
            processBtn.textContent = "جاري معالجة الصوت بالذكاء الاصطناعي...";
            processBtn.disabled = true;

            // محاكاة الإرسال للخادم (سيتم ربطها بـ Backend قريباً)
            setTimeout(() => {
                document.getElementById('resultSection').classList.remove('hidden');
                document.getElementById('outputAudio').src = URL.createObjectURL(recordedBlob);
                document.getElementById('downloadBtn').href = URL.createObjectURL(recordedBlob);
                
                processBtn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> معالجة وتغيير الصوت الآن';
                processBtn.disabled = false;
            }, 2000);
        }

        function shareToMessenger() {
            if (navigator.share) {
                navigator.share({
                    title: 'صوتي المحول',
                    text: 'اسمع هذا الصوت عبر تطبيق الذكاء الاصطناعي!',
                    url: window.location.href,
                }).catch(console.error);
            } else {
                alert('خاصية المشاركة المباشرة تتطلب متصفح هاتف يدعم Web Share API.');
            }
        }
    </script>
</body>
</html>
