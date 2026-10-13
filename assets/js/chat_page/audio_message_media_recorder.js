let rec_mediaRecorder;
let rec_audioChunks;
let rec_mediaStream;
let rec_audio_blob;
let rec_send_to_server = false;
let rec_mimeType = '';
let rec_timerInterval;
let rec_startTime;
let recording_timeout_id;

$(document).ready(async function () {

    const getBestMimeType = () => {
        const types = [
            'audio/webm;codecs=opus',
            'audio/mp4',
            'audio/ogg;codecs=opus',
            'audio/webm'
        ];
        for (const type of types) {
            if (MediaRecorder.isTypeSupported(type)) return type;
        }
        return '';
    };

    let rec_startRecording = async () => {

        try {

            let constraints = {
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                    sampleRate: 44100,
                    channelCount: 1
                }
            };

            rec_mediaStream = await navigator.mediaDevices.getUserMedia(constraints);

            const devices = await navigator.mediaDevices.enumerateDevices();

            let commMicrophone = devices.find(device =>
                device.kind === 'audioinput' && device.label.toLowerCase().includes('communications')
            );

            if (commMicrophone) {
                console.log("Switching to specific mic:", commMicrophone.label);

                rec_mediaStream.getTracks().forEach(track => track.stop());

                constraints.audio.deviceId = {
                    exact: commMicrophone.deviceId
                };
                rec_mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
            }

            const mimeType = getBestMimeType();
            rec_mediaRecorder = new MediaRecorder(rec_mediaStream, {
                mimeType: mimeType,
                audioBitsPerSecond: 128000
            });

            rec_audioChunks = [];

            rec_mediaRecorder.ondataavailable = event => {
                if (event.data.size > 0) {
                    rec_audioChunks.push(event.data);
                }
            };

            rec_mediaRecorder.onstop = () => {

                rec_mimeType = rec_mediaRecorder.mimeType;

                let extension = rec_mimeType.includes('mp4') ? 'm4a': 'webm';
                let rec_fileName = getFileName(extension);

                rec_mediaStream.getTracks().forEach(track => track.stop());

                rec_audio_blob = new Blob(rec_audioChunks, {
                    type: rec_mimeType
                });

                $(".main .chatbox > .footer > .editor .audio_recorder_box > div > .timestamp").text('00:00');
                $(".main .chatbox > .footer > .editor .audio_recorder_box").addClass('d-none');

                if (rec_send_to_server && rec_audioChunks.length > 0) {
                    const audio_message = new File([rec_audio_blob], rec_fileName, {
                        type: rec_mimeType
                    });

                    const content = {
                        'audio_message': audio_message,
                    };

                    send_message(content);
                }
            };

            rec_mediaRecorder.start();
            rec_startTime = performance.now();

            $(".main .chatbox > .footer > .editor .audio_recorder_box > div > .timestamp").replace_text(language_string('recording'));

            rec_updateTimer();
            rec_timerInterval = setInterval(rec_updateTimer, 1000);

            var maxRecordingDuration = 20 * 60 * 1000;

            if (recording_timeout_id) {
                clearTimeout(recording_timeout_id);
            }

            recording_timeout_id = setTimeout(function() {
                $('.send_audio_message').trigger('click');
            }, maxRecordingDuration);

            $(".main .chatbox > .footer > .editor .audio_recorder_box").removeClass('d-none');
        } catch (error) {
            console.error('Error accessing microphone:', error);
            alert_message('Microphone access is required to record audio. Please grant permission.');

            if ($(".main .chatbox > .footer > .editor .toggle_message_toolbar > div > div.toggle_toolbar_button").hasClass("opened")) {
                $(".main .chatbox > .footer > .editor .toggle_message_toolbar > div > div.toggle_toolbar_button").removeClass("opened");
            }

        }
    };

    const rec_updateTimer = () => {
        const elapsedTimeInSeconds = Math.floor((performance.now() - rec_startTime) / 1000);
        $(".main .chatbox > .footer > .editor .audio_recorder_box > div > .timestamp").text(calculateTimeDuration(elapsedTimeInSeconds));
    };

    const rec_stopRecording = () => {
        if (rec_mediaRecorder && rec_mediaRecorder.state === 'recording') {
            clearInterval(rec_timerInterval);
            rec_mediaRecorder.stop();
        }
    };


    function calculateTimeDuration(secs) {
        var hr = Math.floor(secs / 3600);
        var min = Math.floor((secs - (hr * 3600)) / 60);
        var sec = Math.floor(secs - (hr * 3600) - (min * 60));
        min = min < 10 ? "0" + min: min;
        sec = sec < 10 ? "0" + sec: sec;
        return hr <= 0 ? min + ':' + sec: hr + ':' + min + ':' + sec;
    }

    function getFileName(fileExtension) {
        const d = new Date();
        const stamp = `${d.getUTCFullYear()}${d.getUTCMonth() + 1}${d.getUTCDate()}`;
        return `Audio_Message-${stamp}-${getRandomString()}.${fileExtension}`;
    }

    function getRandomString() {
        if (window.crypto && window.crypto.getRandomValues) {
            const a = window.crypto.getRandomValues(new Uint32Array(3));
            let token = '';
            for (const value of a) {
                token += value.toString(36);
            }
            return token;
        } else {
            return (Math.random() * new Date().getTime()).toString(36).replace(/\./g, '');
        }
    }

    $('body').on('click', '.record_audio_message', function () {
        const btnStartRecording = $(this);

        if ($('.main .chatbox > .footer > .editor .audio_recorder_box').hasClass('d-none')) {
            rec_send_to_server = false;
            rec_startRecording();
        }
    });

    $('body').on('click', '.send_audio_message', function () {

        if (recording_timeout_id) {
            clearTimeout(recording_timeout_id);
        }

        rec_send_to_server = true;
        rec_stopRecording();
    });

    $('body').on('click', '.cancel_recording', function () {

        if (recording_timeout_id) {
            clearTimeout(recording_timeout_id);
        }

        rec_send_to_server = false;
        rec_stopRecording();
    });

    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder) {
        console.log("MediaRecorder API is supported in this browser.");
    } else {
        console.log("MediaRecorder API is not supported in this browser.");
    }
});