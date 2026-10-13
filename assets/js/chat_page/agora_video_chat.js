var agora_video_client = null;
var isVideoChatActive = false;
video_chat_available = true;

var audio_only_chat = false;
var publish_local_media = true;
var preferredMicId;
var preferredCamId;
var currentCamId;
var currentMicId;
var mic_mute_on_load = false;
var mute_users_during_call = false;

const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

var videochat_GridContainer = document.getElementById('video-chat-grid');
var audiochat_GridContainer = document.getElementById('audio-chat-grid');
var agora_remoteStreams = {};
var video_chat_formData = new FormData();
let psh_tkLastTapTime = 0;
let psh_tkToggleState = false;

var localTracks = {
    videoTrack: null,
    audioTrack: null
};
var previousTracks = {
    videoTrack: null,
    audioTrack: null
};
var remoteUsers = {};
var options = {
    appid: null,
    channel: null,
    uid: null,
    token: null
};

$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .toggle_chat_window", function(e) {
    $('.main .middle > .video_chat_interface').toggleClass('show_chat_window');
});

$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .leave_video_call", function(e) {
    if (isVideoChatActive) {
        exit_video_chat();
    }
});

$("body").on("click", ".main .chatbox .join_video_call", function(e) {

    if ($('.call_notification').length > 0) {
        if (!$('.call_notification .call_ringtone')[0].paused) {
            $('.call_notification .call_ringtone')[0].pause();
            $('.call_notification .call_ringtone')[0].currentTime = 0;
        }
    }

    $('.main .middle > .mini_game_window').addClass('d-none');
    $('.main .middle > .mini_game_window > .mini_game_embed > div').html('');

    video_chat_formData = new FormData();
    video_chat_formData.append('add', 'video_chat');

    $('.main .middle>.video_chat_interface').removeAttr('group_id');
    $('.main .middle>.video_chat_interface').removeAttr('user_id');

    if ($(".main .chatbox").attr('group_id') !== undefined) {
        video_chat_formData.append('group_id', $(".main .chatbox").attr('group_id'));
        current_video_chat_type = 'group';
        current_video_chat_id = $(".main .chatbox").attr('group_id');
        $('.main .middle>.video_chat_interface').attr('group_id', current_video_chat_id);
    } else if ($(".main .chatbox").attr('user_id') !== undefined) {
        video_chat_formData.append('user_id', $(".main .chatbox").attr('user_id'));
        current_video_chat_type = 'private_chat';
        current_video_chat_id = $(".main .chatbox").attr('user_id');
        $('.main .middle>.video_chat_interface').attr('user_id', current_video_chat_id);
    } else {
        console.log('Error : Failed to fetch conversation info');
        return;
    }


    $('.main .video_chat_container>.icons>span.join_video_chat_now').hide();

    if ($(this).attr('audio_only') !== undefined && $(this).attr('audio_only') === 'yes') {
        video_chat_formData.append('audio_only', true);
        audio_only_chat = true;
        $('.main .video_chat_container>.icons>span.toggle_video_call_camera').hide();
        $('.main .video_chat_container>.icons>span.toggle_screen_share').hide();
    } else {
        audio_only_chat = false;
        $('.main .video_chat_container>.icons>span.toggle_video_call_camera').show();
        $('.main .video_chat_container>.icons>span.toggle_screen_share').show();
    }

    if (user_csrf_token !== null) {
        video_chat_formData.append('csrf_token', user_csrf_token);
    }

    if (user_login_session_id !== null && user_access_code !== null && user_session_time_stamp !== null) {
        video_chat_formData.append('login_session_id', user_login_session_id);
        video_chat_formData.append('access_code', user_access_code);
        video_chat_formData.append('session_time_stamp', user_session_time_stamp);
    }

    $('.main .middle > .video_chat_interface > .video_chat_container > .video_chat_grid').html('');
    $('.video_chat_container > .video_chat_full_view').html('');
    $('.main .middle > .video_chat_interface').removeClass('d-none');
    $('.call_notification').addClass('d-none');

    if (audio_only_chat) {
        $('.main .middle > .video_chat_interface').addClass('audio_only_chat');
    } else {
        $('.main .middle > .video_chat_interface').removeClass('audio_only_chat');
    }


    if (isVideoChatActive) {
        console.log('Video Chat Already Active');
        exit_video_chat();
    } else {

        if ($('.main .chatbox').attr('user_id') !== undefined) {
            current_video_caller_id = $('.main .chatbox').attr('user_id');
        }

        initilazing_video_chat();
        create_video_chat();
    }
});

$("body").on('change', '.video_chat_settings_box .card .select_camera', async function(e) {
    const selectedCamId = $(this).val();

    try {
        await localTracks.videoTrack.setDevice(selectedCamId);
        currentCamId = selectedCamId;
        console.log("Camera switched to:", selectedCamId);
    } catch (error) {
        console.error("Failed to switch camera:", error);
    }
});

$("body").on('change', '.video_chat_settings_box .card .select_microphone', async function(e) {
    const selectedMicId = $(this).val();

    try {
        await localTracks.audioTrack.setDevice(selectedMicId);
        currentMicId = selectedMicId;
        console.log("Mic switched to:", selectedMicId);
    } catch (error) {
        console.error("Failed to switch Mic:", error);
    }
});

async function get_cam_list(context) {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(device => device.kind === 'videoinput');

        const prefix = context === 'livecam' ? '.live_cam_settings_box': '.video_chat_settings_box';

        const camSelect = document.querySelector(`${prefix} .card .select_camera`);

        if (!camSelect) {
            console.warn("Camera select element not found.");
            return;
        }

        camSelect.innerHTML = '';

        if (videoDevices.length === 0) {
            console.warn("No video input devices found.");
            return;
        }

        let currentDeviceId = null;
        if (localTracks && preferredCamId) {
            currentDeviceId = preferredCamId;
        }

        $('.video_chat_settings_box .card .select_camera_box').show();

        videoDevices.forEach((device, index) => {
            const option = document.createElement('option');
            option.value = device.deviceId;
            option.textContent = device.label || `Camera ${index + 1}`;

            if (device.deviceId === currentDeviceId) {
                option.selected = true;
            }

            camSelect.appendChild(option);
        });

    } catch (error) {
        console.error("Error getting camera list:", error);
    }
}

async function get_mic_list(context) {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const audioDevices = devices.filter(device => device.kind === 'audioinput');

        const selector = context === 'livecam'
        ? '.live_cam_settings_box .card select.select_microphone': '.video_chat_settings_box .card select.select_microphone';

        const micSelect = document.querySelector(selector);
        micSelect.innerHTML = '';

        if (audioDevices.length === 0) {
            console.warn("No audio input devices found.");
            return;
        }

        let currentDeviceId = null;
        if (localTracks && preferredMicId) {
            currentDeviceId = preferredMicId;
        }

        audioDevices.forEach((device, index) => {
            const option = document.createElement("option");
            option.value = device.deviceId;
            option.textContent = device.label || `Microphone ${index + 1}`;

            if (device.deviceId === currentDeviceId) {
                option.selected = true;
            }

            micSelect.appendChild(option);
        });

    } catch (error) {
        console.error("Error getting microphone list:", error);
    }
}


$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .toggle_video_call_mic", function(e) {

    if (!agora_video_client) {
        return;
    }

    if (!isVideoChatActive) {
        return;
    }

    if ($(this).find('.mic_muted').hasClass('d-none')) {

        if (localTracks.audioTrack) {
            localTracks.audioTrack.setMuted(true);
        }

        $(this).find('.mic_not_muted').addClass('d-none');
        $(this).find('.mic_muted').removeClass('d-none');

    } else {
        if (localTracks.audioTrack) {
            localTracks.audioTrack.setMuted(false);
        }
        $(this).find('.mic_muted').addClass('d-none');
        $(this).find('.mic_not_muted').removeClass('d-none');

    }

});

$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .join_video_chat_now", function(e) {

    if (!agora_video_client) {
        return;
    }

    if (!isVideoChatActive) {
        return;
    }

    exit_video_chat();

    if ($(".main .chatbox").attr('group_id') !== undefined && $('.main .middle>.video_chat_interface').attr('group_id') !== undefined) {
        if ($(".main .chatbox").attr('group_id') != $('.main .middle>.video_chat_interface').attr('group_id')) {
            alert(language_string('open_chat_to_join_vc'));
            return;
        }
    } else {
        alert(language_string('open_chat_to_join_vc'));
        return;
    }
    setTimeout(function () {
        if (audio_only_chat) {
            $('.main .chatbox .join_video_call').eq(0).addClass('join_video_chat_now').trigger('click');
        } else {
            $('.main .chatbox .join_video_call').eq(1).addClass('join_video_chat_now').trigger('click');
        }
    }, 800);
});

$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .toggle_screen_share", function(e) {

    if (!agora_video_client) {
        return;
    }

    if (!isVideoChatActive) {
        return;
    }

    if ($(this).find('.share_user_screen').hasClass('d-none')) {
        stop_share_device_screen();
        $(this).find('.stop_screen_share').addClass('d-none');
        $(this).find('.share_user_screen').removeClass('d-none');
        $('.main .video_chat_container>.icons>span.toggle_video_call_camera').show();
    } else {
        share_device_screen();
        $(this).find('.share_user_screen').addClass('d-none');
        $(this).find('.stop_screen_share ').removeClass('d-none');
        $('.main .video_chat_container>.icons>span.toggle_video_call_camera').hide();
    }

});


function vc_remote_mute_user(action, group_id) {

    if (!agora_video_client) {
        return;
    }

    if (!isVideoChatActive) {
        return;
    }

    if (action == 'unmute_remote_user') {

        if (system_variable('push_to_talk_feature') === 'enable') {
            if ($('.main .middle>.video_chat_interface').attr('group_id') !== undefined) {
                $('.main .video_chat_container>.icons>span.toggle_push_to_talk').removeClass('d-none');
            }
        } else {
            if (localTracks.audioTrack) {
                localTracks.audioTrack.setMuted(false);
            }
            $('.main .video_chat_container>.icons>span.toggle_video_call_mic').show();
        }
    } else if (action == 'mute_remote_user') {

        if (localTracks.audioTrack) {
            localTracks.audioTrack.setMuted(true);
        }

        $('.main .video_chat_container>.icons>span.toggle_push_to_talk').removeClass('active');
        $('.main .video_chat_container>.icons>span.toggle_video_call_mic').hide();
        $('.main .video_chat_container>.icons>span.toggle_push_to_talk').addClass('d-none');
    }
}


$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .toggle_video_call_camera", function (e) {

    if (!agora_video_client) {
        return;
    }

    if (!isVideoChatActive) {
        return;
    }

    if ($(this).find('.cam_disabled').hasClass('d-none')) {

        if (localTracks.videoTrack) {
            localTracks.videoTrack.setEnabled(false);
        }

        $(this).find('.cam_not_disabled').addClass('d-none');
        $(this).find('.cam_disabled').removeClass('d-none');
    } else {

        if (localTracks.videoTrack) {
            localTracks.videoTrack.setEnabled(true);
        }

        $(this).find('.cam_disabled').addClass('d-none');
        $(this).find('.cam_not_disabled').removeClass('d-none');
    }

});


agora_video_client = AgoraRTC.createClient({
    mode: "rtc", codec: "vp8"
});
agora_video_client.on("user-published", handleUserPublished);
agora_video_client.on("user-unpublished", handleUserUnpublished);
agora_video_client.on("user-left", handleUserLeft);

var create_video_chat = async () => {
    try {
        if (isVideoChatActive) {
            console.log('Video chat is already active. Leaving current session...');
            await leaveChannel();
        }

        if (agora_video_client.connectionState === 'DISCONNECTED') {
            await joinChannel();
            console.log('AgoraRTC client initialized');
        } else {
            console.warn('Client is still connecting or already connected.');
        }
    } catch (error) {
        console.error('Failed to start video chat:', error);
    }
};

var exit_video_chat = async () => {

    $('.main .middle > .video_chat_interface').addClass('d-none');

    $('.toggle_video_call_mic .mic_muted').addClass('d-none');
    $('.toggle_video_call_mic .mic_not_muted').removeClass('d-none');

    $('.toggle_push_to_talk').removeClass('active');
    $('.toggle_push_to_talk').addClass('d-none');

    $('.toggle_screen_share .stop_screen_share').addClass('d-none');
    $('.toggle_screen_share .share_user_screen').removeClass('d-none');

    $('.toggle_video_call_camera .cam_disabled').addClass('d-none');
    $('.toggle_video_call_camera .cam_not_disabled').removeClass('d-none');

    total_vc_users = Object.keys(agora_video_client?.remoteUsers || {}).length;

    leaveChannel();
    stop_update_video_chat_status();
    current_video_chat_type = null;
    current_video_chat_id = null;
};

async function stop_share_device_screen() {

    try {

        if (localTracks.screenTrack) {
            await agora_video_client.unpublish(localTracks.screenTrack);
            localTracks.screenTrack.stop();
            localTracks.screenTrack.close();
            localTracks.screenTrack = null;
        }


        var localParticipantWindow = document.querySelector('.video-window.local-participant-window');

        if (localTracks.videoTrack) {
            localTracks.videoTrack.play(localParticipantWindow);
        }

        if (localTracks.audioTrack && !localTracks.videoTrack) {
            await agora_video_client.publish([localTracks.audioTrack]);
        } else if (localTracks.videoTrack) {
            await agora_video_client.publish([localTracks.videoTrack]);
        }

        if ($('.main .middle > .video_chat_interface > .video_chat_container').hasClass('full_view_container')) {
            if ($('.video_chat_container.full_view_container > .video_chat_full_view > div').length > 0) {
                $('.video_chat_container.full_view_container > .video_chat_full_view > div').appendTo('.main .middle > .video_chat_interface > .video_chat_container > .video_chat_grid');
            }

            $('.main .middle > .video_chat_interface > .video_chat_container').removeClass('full_view_container');
        }

    } catch (error) {
        console.error("Error stopping screenshare: " + error);
    }
}

async function share_device_screen() {
    try {
        var screenTrack = await AgoraRTC.createScreenVideoTrack({
            audio: 'auto',
        });

        if (isVideoChatActive) {

            if (localTracks.videoTrack) {
                await agora_video_client.unpublish(localTracks.videoTrack);
                localTracks.videoTrack.stop();
            }


            var localParticipantWindow = document.querySelector('.video-window.local-participant-window');

            localTracks.screenTrack = screenTrack;
            localTracks.screenTrack.play(localParticipantWindow);

            await agora_video_client.publish(localTracks.screenTrack);
        }

        if ($('.main .middle > .video_chat_interface > .video_chat_container').hasClass('full_view_container')) {
            if ($('.video_chat_container.full_view_container > .video_chat_full_view > div').length > 0) {
                $('.video_chat_container.full_view_container > .video_chat_full_view > div').appendTo('.main .middle > .video_chat_interface > .video_chat_container > .video_chat_grid');
            }

            $('.main .middle > .video_chat_interface > .video_chat_container').removeClass('full_view_container');
        }

        console.log("Screen sharing started");
    } catch (error) {
        console.error("Error sharing screen: " + error);
        $('.main .video_chat_container .toggle_screen_share .stop_screen_share').addClass('d-none');
        $('.main .video_chat_container .toggle_screen_share .share_user_screen').removeClass('d-none');
        $('.main .video_chat_container>.icons>span.toggle_video_call_camera').show();
    }
}

async function checkWebcamAndPermission() {
    try {
        var devices = await navigator.mediaDevices.enumerateDevices();
        var hasWebcam = devices.some(device => device.kind === 'videoinput');

        if (!hasWebcam) {
            return false;
        }

        try {
            var stream = await navigator.mediaDevices.getUserMedia({
                video: true
            });
            stream.getTracks().forEach(track => track.stop());
            return true;
        } catch (error) {
            return false;
        }
    } catch (error) {
        return false;
    }
}

async function checkMicrophonePermission() {
    try {
        var devices = await navigator.mediaDevices.enumerateDevices();
        var hasMicrophone = devices.some(device => device.kind === 'audioinput');

        if (!hasMicrophone) {
            return false;
        }

        try {
            var stream = await navigator.mediaDevices.getUserMedia({
                audio: true
            });

            stream.getTracks().forEach(track => track.stop());
            return true;
        } catch (error) {
            return false;
        }
    } catch (error) {
        return false;
    }
}

async function getPreferredMicrophone() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const audioDevices = devices.filter(device => device.kind === 'audioinput');

        if (audioDevices.length === 0) {
            console.warn("No audio input devices found.");
            return null;
        }

        if (currentMicId) {
            const match = audioDevices.find(device => device.deviceId === currentMicId);
            if (match) {
                console.log(`Using currentMicId: ${match.label}`);
                return currentMicId;
            }
        }

        const communicationsMic = audioDevices.find(device =>
            device.label.toLowerCase().includes("communications")
        );

        if (communicationsMic) {
            console.log(`Using communications microphone: ${communicationsMic.label}`);
            return communicationsMic.deviceId;
        }

        console.log(`Using fallback microphone: ${audioDevices[0].label}`);
        return audioDevices[0].deviceId;

    } catch (error) {
        console.error("Error getting audio input devices:", error);
        return null;
    }
}

async function getPreferredCamera() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(device => device.kind === 'videoinput');

        if (videoDevices.length === 0) {
            console.warn("No video input devices found.");
            return null;
        }

        if (currentCamId) {
            const match = videoDevices.find(device => device.deviceId === currentCamId);
            if (match) {
                console.log(`Using currentCamId: ${match.label}`);
                return currentCamId;
            }
        }

        const preferredCam = videoDevices.find(device =>
            device.label.toLowerCase().includes("front") ||
            device.label.toLowerCase().includes("default")
        );

        if (preferredCam) {
            console.log(`Using preferred camera: ${preferredCam.label}`);
            return preferredCam.deviceId;
        }

        console.log(`Using fallback camera: ${videoDevices[0].label}`);
        return videoDevices[0].deviceId;

    } catch (error) {
        console.error("Error getting video input devices:", error);
        return null;
    }
}

$(document).ready(function () {
    $('.toggle_push_to_talk')
    .on('mousedown touchstart', function (e) {
        // Handle push-to-talk press
        if (isVideoChatActive && !psh_tkToggleState) {
            $(this).addClass('active');
            if (localTracks.audioTrack) {
                localTracks.audioTrack.setMuted(false);
            }
        }

        // Handle double-tap for toggle behavior
        if (e.type === 'touchstart') {
            const currentTime = new Date().getTime();
            const tapLength = currentTime - psh_tkLastTapTime;

            if (tapLength < 300 && tapLength > 0) {
                psh_tkToggleState = !psh_tkToggleState;
                $(this).toggleClass('active', psh_tkToggleState);
                if (localTracks.audioTrack) {
                    localTracks.audioTrack.setMuted(!psh_tkToggleState);
                }
            }

            psh_tkLastTapTime = currentTime;
        }
    })
    .on('dblclick', function () {
        // Double-click toggle
        if (isVideoChatActive) {
            psh_tkToggleState = !psh_tkToggleState;
            $(this).toggleClass('active', psh_tkToggleState);
            if (localTracks.audioTrack) {
                localTracks.audioTrack.setMuted(!psh_tkToggleState);
            }
        }
    })
    .on('mouseup mouseleave touchend touchcancel', function () {
        // Handle push-to-talk release
        if (isVideoChatActive && !psh_tkToggleState) {
            $(this).removeClass('active');
            if (localTracks.audioTrack) {
                localTracks.audioTrack.setMuted(true);
            }
        }
    });
});

async function joinChannel() {

    var skip_vc_camera_confirm = false;

    if ($('.main .chatbox .join_video_call').hasClass('join_video_chat_now')) {
        video_chat_formData.append('join_video_chat_now', true);
        $('.main .chatbox .join_video_call').removeClass('join_video_chat_now');
    } else if (system_variable('view_before_joining_group_vc') === 'enable') {
        skip_vc_camera_confirm = true;
    }

    mute_users_during_call = false;

    var response = await fetch(api_request_url, {
        method: 'POST',
        body: video_chat_formData,
    });

    if (!response.ok) {
        throw new Error('Failed to fetch token from the server');
    }

    try {
        var data = await response.json();
    } catch (error) {
        console.error("Failed to parse server response as JSON:", error);
        exit_video_chat();
        return;
    }

    if (!data || typeof data !== 'object') {
        exit_video_chat();
        console.log('Invalid JSON data');
        return;
    } else if (data.alert_message !== undefined) {
        alert(data.alert_message);
        exit_video_chat();
        return;
    } else if (!data.channel) {
        exit_video_chat();
        console.log('Channel property is missing in JSON data');
        return;
    }

    if (audio_only_chat) {
        var cam_permissionGranted = false;
    } else if (!skip_vc_camera_confirm && current_video_chat_type === 'group' && system_variable('confirm_camera_use') === 'enable') {
        var enableCamera = confirm(language_string('enable_camera_prompt'));
        if (enableCamera) {
            var cam_permissionGranted = await checkWebcamAndPermission();
        } else {
            var cam_permissionGranted = false;
        }
    } else {
        var cam_permissionGranted = await checkWebcamAndPermission();
    }

    var mic_permissionGranted = await checkMicrophonePermission();
    preferredMicId = await getPreferredMicrophone();


    if ($('.video-window.local-participant-window').length > 0) {
        return;
    }

    options = {
        appid: data.app_id,
        channel: data.channel,
        uid: null,
        token: data.token
    };

    if (data.mute_users_during_call !== undefined) {
        mute_users_during_call = true;
    }
    publish_local_media = true;

    var videoTrack_create = null;
    var audioTrack_create = null;
    mic_mute_on_load = false;

    if (system_variable('push_to_talk_feature') === 'enable') {
        if ($(".main .chatbox").attr('group_id') !== undefined) {
            $('.toggle_push_to_talk').removeClass('active d-none');
            $('.main .video_chat_container>.icons>span.toggle_video_call_mic').hide();
            mic_mute_on_load = true;
        } else {
            $('.toggle_push_to_talk').addClass('d-none');
            $('.main .video_chat_container>.icons>span.toggle_video_call_mic').show();
        }
    }

    if (data.subscriber_only !== undefined) {
        publish_local_media = false;

        $('.main .video_chat_container>.icons>span.toggle_video_call_camera').hide();
        $('.main .video_chat_container>.icons>span.toggle_screen_share').hide();
        $('.main .video_chat_container>.icons>span.toggle_video_call_mic').hide();
        $('.main .video_chat_container>.icons>span.toggle_push_to_talk').addClass('d-none');

    } else if (!cam_permissionGranted) {
        $('.main .video_chat_container>.icons>span.toggle_video_call_camera').hide();
    }

    if (data.can_join_later !== undefined) {
        $('.main .video_chat_container>.icons>span.join_video_chat_now').show();
    }

    if (publish_local_media) {
        audioTrack_create = mic_permissionGranted
        ? await AgoraRTC.createMicrophoneAudioTrack({
            encoderConfig: "high_quality_stereo",
            AEC: true,
            AGC: true,
            ANS: true,
            microphoneId: preferredMicId
        }): null;

        await get_mic_list('videochat');

        if (!audio_only_chat && cam_permissionGranted) {
            preferredCamId = await getPreferredCamera();

            videoTrack_create = await AgoraRTC.createCameraVideoTrack({
                cameraId: preferredCamId
            });

            await get_cam_list('videochat');
        } else {
            $('.video_chat_settings_box .card .select_camera_box').hide();
            videoTrack_create = null;
        }
    }

    [options.uid, localTracks.audioTrack, localTracks.videoTrack] = await Promise.all([
        agora_video_client.join(options.appid, options.channel, options.token || null, data.uid),
        audioTrack_create ? audioTrack_create: Promise.resolve(null),
        videoTrack_create ? videoTrack_create: Promise.resolve(null)
    ]);

    if (call_notification_timeout_id) {
        clearTimeout(call_notification_timeout_id);
    }

    $('.call_notification').attr('current_call_id', 0);

    isVideoChatActive = true;

    update_wsocket_data();

    if ($('.main .middle > .video_chat_interface').hasClass('d-none')) {
        exit_video_chat();
        return;
    }

    if (publish_local_media) {
        var localVideoContainer = document.getElementById('video-chat-grid');
        var localVideoElement = document.createElement('div');
        localVideoElement.className = 'video-window local-participant-window';

        var participantName = document.createElement('span');
        participantName.className = 'participant_name';
        participantName.textContent = 'You';

        localVideoElement.appendChild(participantName);

        localVideoContainer.appendChild(localVideoElement);

        $('.local-participant-window').find('.participant_name').addClass('get_info');
        $('.local-participant-window').find('.participant_name').attr('user_id', $('.logged_in_user_id').text());

        if ($(".main .chatbox").attr('group_id') !== undefined) {
            $('.local-participant-window').find('.participant_name').attr('data-group_identifier', $(".main .chatbox").attr('group_id'));
        }

        if (audio_only_chat) {
            $('.local-participant-window').append('<span class="mute_symbol"></span><span class="participant_img"><img src="'+$('.logged_in_user_avatar').attr('src')+'"/></span>');
        }

        if (localTracks.videoTrack) {
            localTracks.videoTrack.play(localVideoElement);
        }

        if (localTracks.videoTrack && localTracks.audioTrack) {
            await agora_video_client.publish(Object.values(localTracks));
        } else if (localTracks.audioTrack) {
            await agora_video_client.publish([localTracks.audioTrack]);
        } else if (localTracks.videoTrack) {
            await agora_video_client.publish([localTracks.videoTrack]);
        }


        if (localTracks.audioTrack) {
            if (mic_mute_on_load) {

                if (system_variable('push_to_talk_feature') === 'enable') {
                    const pt_tooltipInstance = bootstrap.Tooltip.getInstance(document.getElementById('push_to_talk_icon'));
                    if (pt_tooltipInstance) {
                        pt_tooltipInstance.show();
                    }
                }
                localTracks.audioTrack.setMuted(true);
            } else {
                localTracks.audioTrack.setMuted(false);
            }
        }

        console.log("publish success");
        update_video_chat_status();
    }
}

async function leaveChannel() {
    if (!agora_video_client) {
        return;
    }

    if (!isVideoChatActive) {
        return;
    }

    for (trackName in localTracks) {
        var track = localTracks[trackName];
        if (track) {
            track.stop();
            track.close();
            localTracks[trackName] = undefined;
        }
    }

    remoteUsers = {};
    await agora_video_client.leave();

    var agoraVideoContainer = document.getElementById('video-chat-grid');
    agoraVideoContainer.innerHTML = '';

    $('.video_chat_container > .video_chat_full_view').html('');

    isVideoChatActive = false;

    console.log("client leaves channel success");
}

$(document).on("input", ".va_volume_control .vol_control", function () {

    var va_uid = $(this).parent().parent().attr("va_uid");
    var volume = Number($(this).val());
    var remoteUser = agora_video_client.remoteUsers.find(u => u.uid == va_uid);

    if (remoteUser && remoteUser.audioTrack) {
        remoteUser.audioTrack.setVolume(volume);
    }

    var $icon = $(this).siblings(".vol_icon");

    if (volume == 0) {
        $icon.attr("class", "vol_icon bi bi-volume-mute-fill");
    } else if (volume < 50) {
        $icon.attr("class", "vol_icon bi bi-volume-down-fill");
    } else {
        $icon.attr("class", "vol_icon bi bi-volume-up-fill");
    }
});

async function videoChat_channel_subscribe(user, mediaType) {
    var uid = user.uid;
    var unique_id_hash = uniqueCodeHash(uid);

    if (mediaType !== 'no_mediadata') {
        await agora_video_client.subscribe(user, mediaType);
    }

    if (mediaType === 'video' || mediaType === 'audio' || mediaType === 'no_mediadata') {
        var player_id = `player-${unique_id_hash}`;

        var adjust_vol_element = '<div class="va_volume_control">';
        adjust_vol_element += '<i class="vol_icon bi bi-volume-up-fill"></i>';
        adjust_vol_element += '<input class="vol_control" type="range" min="0" max="100" value="100">';
        adjust_vol_element += '</div>';

        fetch_user_info(uid).then(function (userData) {
            var participantUsername = userData.username;

            var group_attribute = '';

            if ($(".main .chatbox").attr('group_id') !== undefined) {
                group_attribute = 'data-group_identifier="'+$(".main .chatbox").attr('group_id')+'"';
            }


            if (audio_only_chat) {
                var agora_player = $(`<div class="video-window player" id="${player_id}" va_uid="${uid}">${adjust_vol_element}<span class="mute_symbol"></span><span class="participant_name">@${participantUsername}</span><span class="participant_img"><img src="${userData.image}"/></span></div>`);
            } else {
                var agora_player = $(`<div class="video-window player" id="${player_id}" va_uid="${uid}">${adjust_vol_element}<span class="mute_symbol"></span><span ${group_attribute} class="participant_name get_info" user_id="${uid}">@${participantUsername}</span></div>`);
            }

            if (!$(`#${player_id}`).length) {

                var vc_options_svg = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 1024 1024"> <path fill="currentColor" d="M983.162 385.156l-111.835-13.828c-1.838-4.702-3.768-9.364-5.784-13.986l69.296-88.855c14.454-18.534 12.824-44.928-3.798-61.546l-114.055-113.991c-16.616-16.608-43.004-18.228-61.526-3.786l-88.823 69.268c-4.624-2.016-9.288-3.948-13.988-5.788l-13.83-111.807c-2.886-23.32-22.696-40.83-46.194-40.83h-161.273c-23.496 0-43.312 17.51-46.194 40.832l-13.828 111.807c-4.698 1.838-9.362 3.768-13.976 5.78l-88.829-69.284c-18.522-14.444-44.916-12.828-61.536 3.788l-114.041 114.017c-16.62 16.614-18.246 43.012-3.792 61.542l69.288 88.833c-2.024 4.624-3.956 9.294-5.8 14.004l-111.811 13.828c-23.318 2.888-40.832 22.698-40.832 46.196v161.279c0 23.5 17.516 43.316 40.842 46.194l111.801 13.806c1.838 4.704 3.768 9.372 5.788 13.988l-69.282 88.833c-14.448 18.528-12.822 44.92 3.788 61.534l114.041 114.061c16.614 16.62 43.014 18.242 61.546 3.792l88.833-69.296c4.612 2.014 9.276 3.948 13.974 5.782l13.828 111.841c2.882 23.32 22.696 40.832 46.194 40.832h161.273c23.496 0 43.31-17.51 46.194-40.832l13.83-111.839c4.702-1.84 9.364-3.77 13.986-5.784l88.849 69.3c18.532 14.45 44.926 12.828 61.542-3.796l114.029-114.061c16.614-16.616 18.234-43.008 3.786-61.536l-69.282-88.817c2.024-4.626 3.956-9.294 5.796-14.004l111.801-13.806c23.322-2.88 40.842-22.696 40.842-46.194v-161.279c-0.006-23.496-17.52-43.306-40.838-46.192zM930.905 551.477l-99.727 12.316c-18.302 2.262-33.544 15.112-38.862 32.772-5.626 18.674-13.12 36.774-22.276 53.806-8.734 16.248-7.050 36.126 4.294 50.67l61.796 79.226-55.848 55.864-79.242-61.81c-14.538-11.336-34.404-13.026-50.654-4.3-17.026 9.144-35.13 16.638-53.81 22.268-17.654 5.322-30.5 20.558-32.762 38.854l-12.338 99.765h-78.984l-12.334-99.765c-2.262-18.292-15.102-33.526-32.75-38.85-18.734-5.65-36.834-13.142-53.798-22.264-16.244-8.738-36.122-7.054-50.672 4.294l-79.23 61.804-55.854-55.87 61.794-79.236c11.342-14.546 13.026-34.412 4.294-50.656-9.136-17.002-16.63-35.108-22.274-53.822-5.324-17.65-20.564-30.494-38.86-32.752l-99.721-12.312v-78.984l99.731-12.334c18.292-2.262 33.526-15.102 38.846-32.75 5.648-18.712 13.144-36.812 22.286-53.8 8.748-16.248 7.066-36.134-4.286-50.684l-61.806-79.24 55.848-55.836 79.24 61.804c14.546 11.344 34.418 13.026 50.67 4.292 16.986-9.13 35.082-16.62 53.792-22.264 17.65-5.324 30.494-20.558 32.756-38.854l12.332-99.733h78.988l12.338 99.737c2.266 18.296 15.114 33.534 32.768 38.854 18.656 5.622 36.758 13.114 53.804 22.27 16.244 8.728 36.11 7.044 50.65-4.298l79.232-61.788 55.854 55.824-61.794 79.236c-11.332 14.534-13.026 34.382-4.316 50.622 9.16 17.080 16.656 35.198 22.276 53.846 5.322 17.65 20.558 30.496 38.854 32.762l99.755 12.338v78.978z"> </path> <path fill="currentColor" d="M511.991 310.304c-111.211 0-201.689 90.487-201.689 201.711 0 111.205 90.477 201.677 201.689 201.677 111.217 0 201.703-90.471 201.703-201.677 0-111.223-90.485-201.711-201.703-201.711zM511.991 620.602c-59.882 0-108.599-48.712-108.599-108.587 0-59.894 48.718-108.621 108.599-108.621 59.888 0 108.611 48.726 108.611 108.621 0 59.876-48.724 108.587-108.611 108.587z"> </path> </svg>';

                var agora_player_options = '<li class="mute_remote_user vc_mute_user" user_id="'+uid+'">'+language_string('mute')+'</li>';
                agora_player_options += '<li class="mute_remote_user vc_unmute_user d-none" unmute="true" user_id="'+uid+'">'+language_string('unmute')+'</li>';
                agora_player_options = '<div class="vc_options"><div class="dropdown_button">'+vc_options_svg+'<ul class="dropdown_list">'+agora_player_options+'</ul></div></div>'

                $("#video-chat-grid").append(agora_player);

                if (mute_users_during_call) {
                    $("#"+player_id).append(agora_player_options);
                }
            }


            if (mediaType === 'video') {
                user.videoTrack.play(`player-${unique_id_hash}`);
            }

            if (mediaType === 'audio') {
                user.audioTrack.play();
            }


        }).catch(function (error) {
            console.log(error);
            var agora_player = $(`<div class="video-window player" va_uid="${uid}" id="${player_id}">${adjust_vol_element}<span class="mute_symbol"></span><span class="participant_name">@${uid}</span></div>`);


            if (!$(`#${player_id}`).length) {
                $("#video-chat-grid").append(agora_player);
            }


            if (mediaType === 'video') {
                user.videoTrack.play(`player-${unique_id_hash}`);
            }

            if (mediaType === 'audio') {
                user.audioTrack.play();
            }
        });


    }
}

agora_video_client.enableAudioVolumeIndicator();

agora_video_client.on("volume-indicator", function (volumes) {
    $('.video_chat_grid > div').removeClass('talking');

    volumes.forEach(function (volume) {
        if (volume.level > 10) {
            const uid = volume.uid;
            const unique_id_hash = uniqueCodeHash(uid);

            if (current_logged_user_id && current_logged_user_id == uid) {
                $(`.video_chat_grid > div.local-participant-window`).addClass('talking');
            } else {
                $(`.video_chat_grid > #player-${unique_id_hash}`).addClass('talking');
            }
        }
    });
});

agora_video_client.on("user-joined", async (user) => {
    const uid = user.uid;
    const unique_id_hash = uniqueCodeHash(uid);
    const player_id = `player-${unique_id_hash}`;
    if (system_variable('view_before_joining_group_vc') !== 'enable') {
        if (!$(`#${player_id}`).length) {
            videoChat_channel_subscribe(user, 'no_mediadata');
        }
    }
});

function handleUserPublished(user, mediaType) {

    var id = user.uid;

    if (mediaType === "audio") {
        var unique_id_hash = uniqueCodeHash(id);
        var player_id = `player-${unique_id_hash}`;
        $(`#${player_id}`).find('span.mute_symbol').removeClass('muted');
        $(`#${player_id}`).removeClass('muted');
    }

    remoteUsers[id] = user;
    videoChat_channel_subscribe(user, mediaType);
}

function handleUserLeft(user, mediaType) {
    var id = user.uid;
    var unique_id_hash = uniqueCodeHash(id);

    if (mediaType === 'video' || mediaType === 'audio') {
        delete remoteUsers[id];
    }

    if ($(`#player-${unique_id_hash}`).parent().hasClass('video_chat_full_view')) {
        $('.main .middle > .video_chat_interface > .video_chat_container').removeClass('full_view_container');
    }

    $(`#player-${unique_id_hash}`).remove();

    var total_vclients = $('.main .middle > .video_chat_interface > .video_chat_container > .video_chat_grid > div').length;

    if (total_vclients === 0 && $('.main .middle > .video_chat_interface > .video_chat_container').hasClass('full_view_container')) {
        if ($('.video_chat_container.full_view_container > .video_chat_full_view > div').length > 0) {
            $('.video_chat_container.full_view_container > .video_chat_full_view > div').appendTo('.main .middle > .video_chat_interface > .video_chat_container > .video_chat_grid');
        }

        $('.main .middle > .video_chat_interface > .video_chat_container').removeClass('full_view_container');
    }
}

function handleUserUnpublished(user, mediaType) {
    if (mediaType === "audio") {
        console.log(`User ${user.uid} has muted their audio.`);

        var unique_id_hash = uniqueCodeHash(user.uid);
        var player_id = `player-${unique_id_hash}`;
        $(`#${player_id}`).find('span.mute_symbol').addClass('muted');
        $(`#${player_id}`).addClass('muted');

    } else if (mediaType === "video") {
        console.log(`User ${user.uid} has turned off their video.`);
    }
}

/* --- LIVE CAMERA FEATURE --- */

var live_cam_room = null;
var live_cam_is_active = false;
var current_live_intent = null;
var current_live_privacy = null;
const live_cam_active_polls = {};
let privatePollingInterval = null;
let live_cam_group_id = null;

let lc_local_tracks = {
    videoTrack: null,
    audioTrack: null,
    screenTrack: null,
    screenAudio: null
};

async function live_cam_init_connection(action = 'view', privacy = 'public') {

    const live_cam_target_group = $(".main .chatbox").attr('group_id');
    if (!live_cam_target_group) return false;

    if (live_cam_group_id && live_cam_group_id !== live_cam_target_group) {
        await end_live_camera();
    }

    if (!live_cam_group_id) {
        live_cam_group_id = live_cam_target_group;
    }

    const get_live_cam_post_data = () => {
        let post_data = {
            add: 'live_camera',
            group_id: live_cam_group_id,
            intent: 'view',
            privacy: privacy
        };

        if (user_csrf_token !== null) {
            post_data["csrf_token"] = user_csrf_token;
        }

        if (user_login_session_id !== null && user_access_code !== null && user_session_time_stamp !== null) {
            post_data["login_session_id"] = user_login_session_id;
            post_data["access_code"] = user_access_code;
            post_data["session_time_stamp"] = user_session_time_stamp;
        }
        return post_data;
    };

    if (live_cam_room && live_cam_room.connectionState === 'CONNECTED') {
        try {
            await $.ajax({
                url: api_request_url,
                method: 'POST',
                dataType: 'json',
                data: get_live_cam_post_data(),
            });

            current_live_intent = action;
            current_live_privacy = privacy;

            return true;
        } catch (error) {
            console.error("Failed to update live cam intent on server:", error);
            return false;
        }
    }

    initilazing_live_cam();

    try {
        const live_cam_data = await $.ajax({
            url: api_request_url,
            method: 'POST',
            dataType: 'json',
            data: get_live_cam_post_data(),
        });

        if (!live_cam_data || Object.keys(live_cam_data).length === 0) {
            return false;
        }

        current_live_intent = action;
        current_live_privacy = privacy;

        live_cam_room = AgoraRTC.createClient({
            mode: "rtc", codec: "vp8"
        });

        live_cam_room.on("user-published", async (user, mediaType) => {
            const identity = user.uid;
            const $existingBox = $(`#live_cam_box_${identity}`);

            await live_cam_room.subscribe(user, mediaType);

            if (mediaType === "video" && $existingBox.length > 0) {
                let timeout = 0;
                while (!user.videoTrack && timeout < 50) {
                    await new Promise(r => setTimeout(r, 100));
                    timeout++;
                }

                if (user.videoTrack) {
                    live_cam_render_video(user.videoTrack, null, identity, false, false, true);
                    console.log(`Video track attached for ${identity}`);
                }
            }

            if (mediaType === "audio" && $existingBox.length > 0) {
                user.audioTrack.play();

                const $slider = $existingBox.find('.live_cam_volume_slider');
                if ($slider.length > 0) {
                    const currentVolume = parseFloat($slider.val());
                    user.audioTrack.setVolume(Math.floor(currentVolume * 100));
                }
            }
        });

        live_cam_room.on("user-left", (user) => {
            const identity = user.uid;

            var remove_data = {
                remove: 'live_camera_viewers',
                user_id: identity,
                group_id: live_cam_group_id
            };

            if (user_csrf_token !== null) remove_data["csrf_token"] = user_csrf_token;
            if (user_login_session_id !== null) {
                remove_data["login_session_id"] = user_login_session_id;
                remove_data["access_code"] = user_access_code;
                remove_data["session_time_stamp"] = user_session_time_stamp;
            }

            $.post(api_request_url, remove_data);

            $(`#live_cam_box_${identity}`).find('video, audio').each(function() {
                this.pause();
                this.srcObject = null;
            });

            $(`#live_cam_box_${identity}`).remove();

            setTimeout(() => {
                if ($('#live_cameras').children().length === 0) {
                    $(".live_camera_interface").addClass('d-none');
                    $('.main .chatbox .resize_live_camera').addClass('d-none');
                }
            }, 100);
        });

        await live_cam_room.join(
            live_cam_data.app_id,
            live_cam_data.channel,
            live_cam_data.token,
            live_cam_data.uid
        );

        console.log("Agora LiveCam Connection Successful");
        return true;

    } catch (error) {
        console.error("Agora LiveCam Connection Failed:", error);
        live_cam_group_id = null;
        return false;
    }
}

const checkPendingPrivateRequests = (groupId) => {
    if (!live_cam_room || current_live_intent !== 'broadcast') {
        stopPrivatePolling();
        return;
    }

    let post_data = {
        load: 'pending_live_cam_viewers',
        group_id: groupId
    };

    if (user_csrf_token !== null) post_data["csrf_token"] = user_csrf_token;
    if (user_login_session_id !== null) {
        post_data["login_session_id"] = user_login_session_id;
        post_data["access_code"] = user_access_code;
        post_data["session_time_stamp"] = user_session_time_stamp;
    }

    $.post(api_request_url, post_data, function(response) {
        if (isJSON(response)) {
            response = $.parseJSON(response);
        }

        if (response && response.has_request) {
            stopPrivatePolling();

            live_cam_confirm(response.alert_message, function(approved) {
                const decision = approved ? 'approve': 'reject';

                let update_data = {
                    update: 'live_camera_viewers',
                    user_id: response.user_id,
                    status: decision,
                    group_id: groupId
                };

                if (user_csrf_token !== null) update_data["csrf_token"] = user_csrf_token;
                if (user_login_session_id !== null) {
                    update_data["login_session_id"] = user_login_session_id;
                    update_data["access_code"] = user_access_code;
                    update_data["session_time_stamp"] = user_session_time_stamp;
                }

                $.post(api_request_url, update_data, function() {
                    startPrivatePolling(groupId);
                });
            });
        }
    });
};

const startPrivatePolling = (groupId) => {
    if (!groupId) return;
    if (!privatePollingInterval) {
        privatePollingInterval = setInterval(() => checkPendingPrivateRequests(groupId), 5000);
    }
};

const stopPrivatePolling = () => {
    if (privatePollingInterval) {
        clearInterval(privatePollingInterval);
        privatePollingInterval = null;
    }
};

function live_cam_stop_polling(user_id) {
    const uid = String(user_id);
    if (live_cam_active_polls[uid]) {
        clearInterval(live_cam_active_polls[uid]);
        delete live_cam_active_polls[uid];
    }
}

function live_cam_confirm(message, callback) {
    $('#live_cam_confirm_modal').remove();

    const modalHtml = `
    <div class="modal fade chat_page_modal" id="live_cam_confirm_modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
    <div class="modal-header">
    <h5 class="modal-title">
    <i class="fa fa-video-camera mr-2 text-primary"></i>
    ${language_string('camera_request_title')}
    </h5>
    </div>
    <div class="modal-body">
    <p class="mb-0" style="font-size: 1.1rem;">${message}</p>
    </div>
    <div class="modal-footer">
    <button type="button" class="btn btn-outline-danger" id="live_cam_confirm_reject">
    ${language_string('reject')}
    </button>
    <button type="button" class="btn btn-success" id="live_cam_confirm_approve">
    ${language_string('approve')}
    </button>
    </div>
    </div>
    </div>
    </div>`;

    $('body').append(modalHtml);
    const $modal = $('#live_cam_confirm_modal');
    $modal.modal('show');

    $('#live_cam_confirm_approve').on('click', function() {
        $modal.modal('hide');
        callback(true);
    });

    $('#live_cam_confirm_reject').on('click', function() {
        $modal.modal('hide');
        callback(false);
    });

    $modal.on('hidden.bs.modal', function () {
        $(this).remove();
    });
}


$(document).on('click', '.live_cam_btn_broadcast', async function() {
    const $live_cam_btn = $(this);
    const broadcastType = $live_cam_btn.data('type') || 'public';

    if ($live_cam_btn.hasClass('live-active')) {
        try {
            if (lc_local_tracks.videoTrack) {
                await live_cam_room.unpublish(lc_local_tracks.videoTrack);
                lc_local_tracks.videoTrack.stop();
                lc_local_tracks.videoTrack.close();
                lc_local_tracks.videoTrack = null;
            }
            if (lc_local_tracks.audioTrack) {
                await live_cam_room.unpublish(lc_local_tracks.audioTrack);
                lc_local_tracks.audioTrack.stop();
                lc_local_tracks.audioTrack.close();
                lc_local_tracks.audioTrack = null;
            }

            stopPrivatePolling();

            var post_data = {
                remove: 'live_camera', group_id: live_cam_group_id
            };

            if (user_csrf_token !== null) post_data["csrf_token"] = user_csrf_token;
            if (user_login_session_id !== null) {
                post_data["login_session_id"] = user_login_session_id;
                post_data["access_code"] = user_access_code;
                post_data["session_time_stamp"] = user_session_time_stamp;
            }

            $.post(api_request_url, post_data);

            $(`#live_cam_box_${live_cam_room.uid}`).remove();

            if ($('#live_cameras').children().length === 0) {
                $('.main .chatbox .resize_live_camera').addClass('d-none');
                $(".live_camera_interface").addClass('d-none');
            }

            $(".start_broad_cast_icon").removeClass('d-none');
            $(".stop_broad_cast_icon").addClass('d-none');
            $('.live_cam_btn_broadcast').removeClass('live-active');
            $(".live_cam_controls_container").addClass('d-none');
            $(".main .chatbox > .header > .icons .toggle_lc_viewers").addClass('d-none');

            if (live_cam_room) {
                await live_cam_room.leave();
                live_cam_room = null;
                console.log("✅ Agora Room left successfully");
            }

            current_live_intent = null;
            current_live_privacy = null;
            live_cam_group_id = null;

        } catch (err) {
            console.error("Error stopping Agora broadcast:", err);
        }
    } else {
        const isConnected = await live_cam_init_connection('broadcast', broadcastType);
        if (!isConnected) return;

        try {
            const micId = await getPreferredMicrophone();
            const camId = await getPreferredCamera();

            await get_mic_list('livecam');
            await get_cam_list('livecam');

            try {
                const videoConfig = {
                    encoderConfig: "720p_1",
                    cameraId: camId
                };
                const audioConfig = {
                    microphoneId: micId,
                    AEC: true, ANS: true, AGC: true
                };

                [lc_local_tracks.audioTrack, lc_local_tracks.videoTrack] = await AgoraRTC.createMicrophoneAndCameraTracks(audioConfig, videoConfig);
            } catch (trackError) {
                console.warn("Failed with preferred devices, trying defaults:", trackError);
                [lc_local_tracks.audioTrack, lc_local_tracks.videoTrack] = await AgoraRTC.createMicrophoneAndCameraTracks();
            }

            if (lc_local_tracks.videoTrack && lc_local_tracks.audioTrack) {
                await live_cam_room.publish([lc_local_tracks.audioTrack, lc_local_tracks.videoTrack]);

                const isPrivate = (broadcastType === 'private');

                live_cam_render_video(lc_local_tracks.videoTrack, null, live_cam_room.uid, true, isPrivate);

                var post_data = {
                    add: 'live_camera',
                    group_id: live_cam_group_id,
                    privacy: broadcastType,
                    intent: 'broadcast'
                };

                if (user_csrf_token !== null) post_data["csrf_token"] = user_csrf_token;
                if (user_login_session_id !== null) {
                    post_data["login_session_id"] = user_login_session_id;
                    post_data["access_code"] = user_access_code;
                    post_data["session_time_stamp"] = user_session_time_stamp;
                }

                $.post(api_request_url, post_data);

                if (isPrivate) {
                    startPrivatePolling(live_cam_group_id);
                }

                const $view_lc_btn = $(".main .chatbox > .header > .icons .toggle_lc_viewers");
                $view_lc_btn.attr('data-group_id', live_cam_group_id);
                $view_lc_btn.data('group_id', live_cam_group_id);
                $view_lc_btn.attr('last_lc_viewer_id', 0).attr('total_lc_viewers', 0);
                $view_lc_btn.data('group_id', live_cam_group_id).removeClass('d-none');
                
                $('.live_cam_controls_container').removeClass('d-none');
                $(".start_broad_cast_icon").addClass('d-none');
                $(".stop_broad_cast_icon").removeClass('d-none');
                $('.live_cam_btn_broadcast').addClass('live-active');
            }
        } catch (error) {
            console.error("Agora Broadcast Start Failed:", error);
            if (lc_local_tracks.videoTrack) lc_local_tracks.videoTrack.close();
            if (lc_local_tracks.audioTrack) lc_local_tracks.audioTrack.close();
        }
    }
});


async function live_cam_view(user_id, group_id) {
    const live_cam_identity = String(user_id);
    const $btn = $(`.live_cam_btn_view[user_id="${user_id}"]`);

    if (live_cam_active_polls[user_id]) return;

    if (live_cam_group_id && group_id && live_cam_group_id !== group_id) {
        await end_live_camera();
    }

    const activeVideoCount = $('.live_cam_video_box').length;
    if (activeVideoCount >= 8) {
        alert_message(language_string('exceeded_max_broadcasts_view'));
        $btn.removeClass('live-cam-processing');
        return false;
    }

    try {
        const live_cam_is_connected = await live_cam_init_connection('view', current_live_privacy || 'public');
        if (!live_cam_is_connected) {
            $btn.removeClass('live-cam-processing');
            return;
        }

        let live_cam_participant = null;
        let retries = 0;

        while (!live_cam_participant && retries < 30) {
            live_cam_participant = live_cam_room.remoteUsers.find(u =>
                String(u.uid) === live_cam_identity
            );
            if (!live_cam_participant) {
                await new Promise(resolve => setTimeout(resolve, 100));
                retries++;
            }
        }

        if (!live_cam_participant) {
            var post_data = {
                remove: 'live_camera',
                user_id: user_id,
                group_id: group_id
            };

            if (user_csrf_token !== null) post_data["csrf_token"] = user_csrf_token;
            if (user_login_session_id !== null) {
                post_data["login_session_id"] = user_login_session_id;
                post_data["access_code"] = user_access_code;
                post_data["session_time_stamp"] = user_session_time_stamp;
            }

            await $.post(api_request_url, post_data, null, 'json');

            refresh_group_members(group_id);
            $btn.removeClass('live-cam-processing');
            return;
        }

        var post_data = {
            add: 'live_camera_viewers',
            user_id: user_id,
            group_id: group_id
        };

        if (user_csrf_token !== null) post_data["csrf_token"] = user_csrf_token;
        if (user_login_session_id !== null) {
            post_data["login_session_id"] = user_login_session_id;
            post_data["access_code"] = user_access_code;
            post_data["session_time_stamp"] = user_session_time_stamp;
        }

        const live_cam_response = await $.post(api_request_url, post_data, null, 'json');

        if (live_cam_response.status === 'approved') {
            $btn.removeClass('live-cam-processing');

            if (live_cam_participant.hasVideo) {
                await live_cam_room.subscribe(live_cam_participant, "video");
            }
            if (live_cam_participant.hasAudio) {
                await live_cam_room.subscribe(live_cam_participant, "audio");
            }

            live_cam_render_video(live_cam_participant.videoTrack, null, live_cam_identity, false, false, true);

        } else if (live_cam_response.status === 'pending') {
            if (live_cam_response?.alert_message) alert_message(live_cam_response.alert_message);

            live_cam_start_polling(user_id, live_cam_participant);

        } else if (live_cam_response.status === 'not_found') {
            refresh_group_members(live_cam_response.group_id);
            $btn.removeClass('live-cam-processing');
        } else {
            if (live_cam_response?.alert_message) alert_message(live_cam_response.alert_message);
            $btn.removeClass('live-cam-processing');
        }

    } catch (live_cam_error) {
        $btn.removeClass('live-cam-processing');
        $(`#live_cam_box_${live_cam_identity}`).remove();
        console.error("Agora View request failed:", live_cam_error);
    }
}

function refresh_group_members(group_id) {
    if ($('.main .aside > .site_records > .current_record').attr('load') == 'group_members') {
        var current_group_id = $('.main .aside>.site_records>.current_record>.record_info .data_attributes > span').attr('data-group_id');
        if (current_group_id == group_id) {
            $(".main .aside > .site_records > .current_record").removeClass('loading');
            $('.main .aside > .site_records > .current_record').attr('disable_preloader', true);
            $(".main .aside > .site_records > .current_record").attr('data-group_id', current_group_id)
            load_aside($(".main .aside > .site_records > .current_record"));
            $('.main .aside > .site_records > .current_record').removeAttr('disable_preloader');
            $('.main .aside > .site_records > .current_record').removeAttr('data-group_id');
        }
    }
}

async function live_cam_initiate_subscription(live_cam_participant) {
    const uid = live_cam_participant.uid;

    if (!live_cam_participant.hasVideo) {
        console.warn(`User ${uid} has no video tracks available.`);
        return false;
    }

    try {
        await live_cam_room.subscribe(live_cam_participant, "video");

        if (live_cam_participant.hasAudio) {
            await live_cam_room.subscribe(live_cam_participant, "audio");
        }

        let timeout = 0;
        while (!live_cam_participant.videoTrack && timeout < 50) {
            await new Promise(resolve => setTimeout(resolve, 100));
            timeout++;
        }

        if (live_cam_participant.videoTrack) {
            live_cam_render_video(
                live_cam_participant.videoTrack,
                live_cam_participant.audioTrack || null,
                uid,
                false,
                false,
                true
            );
        } else {
            console.error("Agora video track failed to arrive after subscription.");
        }
    } catch (err) {
        console.error("Subscription failed:", err);
    }
}


function live_cam_start_polling(user_id, live_cam_participant) {
    const uid = String(user_id);
    const $btn = $(`.live_cam_btn_view[user_id="${uid}"]`);

    live_cam_stop_polling(uid);

    live_cam_active_polls[uid] = setInterval(async () => {
        try {
            var post_data = {
                add: 'live_camera_viewers',
                user_id: uid,
                group_id: live_cam_group_id
            };

            if (user_csrf_token !== null) post_data["csrf_token"] = user_csrf_token;
            if (user_login_session_id !== null) {
                post_data["login_session_id"] = user_login_session_id;
                post_data["access_code"] = user_access_code;
                post_data["session_time_stamp"] = user_session_time_stamp;
            }

            const live_cam_check = await $.post(api_request_url, post_data, null, 'json');

            if (live_cam_check.status === 'approved') {
                live_cam_stop_polling(uid);
                $btn.removeClass('live-cam-processing');

                live_cam_initiate_subscription(live_cam_participant);

            } else if (live_cam_check.status === 'rejected') {
                live_cam_stop_polling(uid);
                $btn.removeClass('live-cam-processing');

                if (live_cam_check?.alert_message) {
                    alert_message(live_cam_check.alert_message);
                }
            }

        } catch (e) {
            console.error("Polling error for Agora user, stopping:", e);
            live_cam_stop_polling(uid);
            $btn.removeClass('live-cam-processing');
        }
    }, 5000);
}

function live_cam_stop_polling(user_id) {
    const uid = String(user_id);
    if (live_cam_active_polls[uid]) {
        clearInterval(live_cam_active_polls[uid]);
        delete live_cam_active_polls[uid];
    }
}


$(document).on('click', '.live_cam_btn_view', function() {
    const $btn = $(this);
    const userId = $btn.attr('user_id');
    const groupId = $btn.attr('group_id');

    if ($btn.hasClass('live-cam-processing')) {
        return;
    }
    const $existingBox = $(`#live_cam_box_${userId}`);

    if ($existingBox.length > 0) {
        $existingBox.find('.live_cam_close_btn').trigger('click');
        return;
    }

    if (userId) {
        $btn.addClass('live-cam-processing');
        open_column('second');
        live_cam_view(userId, groupId);
    }
});


async function live_cam_render_video(videoTrack, audioTrack, identity, isLocal, isPrivate = false, isReplace = false) {

    let $box = $(`#live_cam_box_${identity}`);

    if ($box.length > 0 && isReplace) {
        const $videoTarget = $box.find('.live-cam-video-target');
        console.log('Agora: Replacing Track');

        $videoTarget.empty();

        if (videoTrack) {
            videoTrack.play($videoTarget[0]);
            console.log(`Agora video track playing for ${identity}`);
        }

        if (audioTrack && !isLocal) {
            audioTrack.play();
        }
        return;
    }

    if ($box.length > 0) return;

    if ($(".live_camera_interface").hasClass('d-none')) {
        $(".live_camera_interface").removeClass('d-none');
        $('.main .chatbox .resize_live_camera').removeClass('d-none');
    }

    const privacyBadge = isPrivate ? `<span class="badge-private" style="color:red; font-size:10px;"> (${language_string('private_camera')})</span>`: '';

    let username = identity;
    if (!isLocal) {
        try {
            const userData = await fetch_user_info(identity);
            username = '@'+userData.username;
        } catch (error) {
            username = identity;
        }
    } else {
        username = language_string('my_camera');
    }

    const volumeControl = !isLocal ? `
    <div class="live-cam-volume-wrapper position-relative d-inline-flex align-items-center ms-auto">
    ${isIOS ? `
    <button class="btn btn-sm btn-link text-white p-0 live_cam_mute_btn" data-id="${identity}">
    <i class="bi bi-volume-up"></i>
    </button>
    `: `
    <button class="btn btn-sm btn-link text-white p-0 live_cam_volume_toggle">
    <i class="bi bi-volume-up"></i>
    </button>
    <div class="live-cam-volume-dropdown d-none">
    <input type="range" class="live_cam_volume_slider" data-id="${identity}" min="0" max="100" step="5" value="100" style="width: 80px; height: 4px;">
    </div>
    `}
    </div>`: '';

    const live_cam_html = `
    <div id="live_cam_box_${identity}" class="live-cam-item">
    <div class="live-cam-header d-flex align-items-center justify-content-between">
    <span class="text-truncate" style="max-width: 120px;">
    ${isLocal ? username + privacyBadge: username}
    </span>
    <div class="live-cam-actions d-flex align-items-center">
    ${volumeControl}
    <button class="live_cam_fullscreen_btn btn btn-sm btn-link text-white p-0 ms-2" data-id="${identity}">
    <i class="bi bi-fullscreen"></i>
    </button>
    <button class="live_cam_close_btn btn btn-sm btn-link text-white p-0 ms-2" data-id="${identity}" data-local="${isLocal}">
    <i class="bi bi-x-lg"></i>
    </button>
    </div>
    </div>
    <div class="live-cam-video-target"></div>
    </div>`;

    $('#live_cameras').append(live_cam_html);

    if (videoTrack) {
        videoTrack.play($(`#live_cam_box_${identity} .live-cam-video-target`)[0]);
    }

    if (audioTrack && !isLocal) {
        audioTrack.play();

        const $existingSlider = $(`.live_cam_volume_slider[data-id="${identity}"]`);
        if ($existingSlider.length > 0) {
            const currentVolume = parseInt($existingSlider.val());
            audioTrack.setVolume(currentVolume);
        }
    }
}

$(document).on('click', '#live_cam_share_screen', async function() {
    if (!live_cam_room) return;

    const $btn = $(this);
    const $camBtn = $('#live_cam_toggle_video');
    const isSharing = !!lc_local_tracks.screenTrack;

    $('#live_cam_open_settings').removeClass('d-none');

    try {
        if (!isSharing) {
            if (lc_local_tracks.videoTrack) {
                await live_cam_room.unpublish(lc_local_tracks.videoTrack);
                lc_local_tracks.videoTrack.stop();
                lc_local_tracks.videoTrack.close();
                lc_local_tracks.videoTrack = null;
            }

            lc_local_tracks.screenTrack = await AgoraRTC.createScreenVideoTrack({
                encoderConfig: "1080p_1",
            }, "auto");

            let videoTrack, audioTrack;
            if (Array.isArray(lc_local_tracks.screenTrack)) {
                [videoTrack, audioTrack] = lc_local_tracks.screenTrack;
                lc_local_tracks.screenTrack = videoTrack;
                lc_local_tracks.screenAudio = audioTrack;
            } else {
                videoTrack = lc_local_tracks.screenTrack;
            }

            await live_cam_room.publish(filterNonNull([videoTrack, audioTrack]));

            $('#live_cam_open_settings').addClass('d-none');
            $btn.addClass('live_cam_off').find('i').removeClass('bi-display').addClass('bi-x-circle-fill');
            $camBtn.addClass('live_cam_off').find('i').removeClass('bi-camera-video-fill').addClass('bi-camera-video-off-fill');

            live_cam_render_video(videoTrack, audioTrack, live_cam_room.uid, true, false, true);

            videoTrack.on("track-ended", () => {
                $btn.trigger('click');
            });

        } else {
            await live_cam_room.unpublish(filterNonNull([lc_local_tracks.screenTrack, lc_local_tracks.screenAudio]));
            lc_local_tracks.screenTrack.close();
            if (lc_local_tracks.screenAudio) lc_local_tracks.screenAudio.close();
            lc_local_tracks.screenTrack = null;
            lc_local_tracks.screenAudio = null;

            $btn.removeClass('live_cam_off').find('i').removeClass('bi-x-circle-fill').addClass('bi-display');

            lc_local_tracks.videoTrack = await AgoraRTC.createCameraVideoTrack();
            await live_cam_room.publish(lc_local_tracks.videoTrack);

            $camBtn.removeClass('live_cam_off').find('i').removeClass('bi-camera-video-off-fill').addClass('bi-camera-video-fill');

            live_cam_render_video(lc_local_tracks.videoTrack, null, live_cam_room.uid, true, false, true);
        }
    } catch (error) {
        console.error("Agora Screen share error:", error);
        alert_message("Screen share failed or cancelled.");
    }
});

function filterNonNull(arr) {
    return arr.filter(i => i !== null && i !== undefined);
}

$(document).on('click', '#live_cam_open_settings', function(e) {
    $('.live_camera_interface > .live_cam_settings_box').removeClass('d-none');
});

$(document).on('click', '.live_camera_interface > .live_cam_settings_box .btn-close', function(e) {
    $('.live_camera_interface > .live_cam_settings_box').addClass('d-none');
});

$(document).on('click', '.live_cam_volume_toggle', function(e) {
    e.stopPropagation();
    $('.live-cam-volume-dropdown').not($(this).siblings('.live-cam-volume-dropdown')).addClass('d-none');
    $(this).siblings('.live-cam-volume-dropdown').toggleClass('d-none');
});

$(document).on('click', function(e) {
    if (!$(e.target).closest('.live-cam-volume-wrapper').length) {
        $('.live-cam-volume-dropdown').addClass('d-none');
    }
});

$(document).on('click', '.live_cam_mute_btn', function() {
    const $btn = $(this);
    const $icon = $btn.find('i');
    const identity = String($btn.data('id'));

    if (live_cam_room) {
        const user = live_cam_room.remoteUsers.find(u => String(u.uid) === identity);

        if (user && user.audioTrack) {
            const isCurrentlyMuted = user.audioTrack.getVolumeLevel() === 0;
            const newVolume = isCurrentlyMuted ? 100: 0;

            user.audioTrack.setVolume(newVolume);

            if (newVolume === 0) {
                $icon.removeClass('bi-volume-up').addClass('bi-volume-mute text-danger');
            } else {
                $icon.removeClass('bi-volume-mute text-danger').addClass('bi-volume-up');
            }
        }
    }
});

$(document).on('input', '.live_cam_volume_slider', function() {
    const $this = $(this);
    const identity = String($this.data('id'));
    const volume = parseInt($this.val());
    const $icon = $this.closest('.live-cam-volume-wrapper').find('.live_cam_volume_toggle i');

    $icon.removeClass('bi-volume-up bi-volume-down bi-volume-mute text-danger');
    if (volume === 0) {
        $icon.addClass('bi-volume-mute text-danger');
    } else if (volume < 50) {
        $icon.addClass('bi-volume-down');
    } else {
        $icon.addClass('bi-volume-up');
    }

    if (live_cam_room && identity) {
        const user = live_cam_room.remoteUsers.find(u => String(u.uid) === identity);

        if (user && user.audioTrack) {
            user.audioTrack.setVolume(volume);
        }
    }
});

$(document).on('click', '.live_cam_close_btn', async function() {
    const live_cam_id = $(this).data('id');
    const isLocal = $(this).data('local');

    if (isLocal === true || isLocal === "true") {
        $('.live_cam_btn_broadcast.live-active').eq(0).trigger('click');
    } else {
        var post_data = {
            remove: 'live_camera_viewers',
            user_id: live_cam_id,
            group_id: live_cam_group_id,
        };

        if (user_csrf_token !== null) post_data["csrf_token"] = user_csrf_token;
        if (user_login_session_id !== null) {
            post_data["login_session_id"] = user_login_session_id;
            post_data["access_code"] = user_access_code;
            post_data["session_time_stamp"] = user_session_time_stamp;
        }

        $.post(api_request_url, post_data);

        if (live_cam_room) {
            const remoteUser = live_cam_room.remoteUsers.find(u => String(u.uid) === String(live_cam_id));
            if (remoteUser) {
                await live_cam_room.unsubscribe(remoteUser);
            }
        }

        const $targetBox = $(`#live_cam_box_${live_cam_id}`);
        $targetBox.remove();
    }

    if ($('#live_cameras').children().length === 0) {
        $(".live_camera_interface").addClass('d-none');
        $('.main .chatbox .resize_live_camera').addClass('d-none');
    }
});

window.addEventListener('pagehide', () => {
    if (live_cam_room && current_live_intent === 'broadcast') {
        end_live_camera();
    }
});

async function end_live_camera() {
    if (window.is_ending_live) return;
    window.is_ending_live = true;

    try {
        stopPrivatePolling();
        if (typeof live_cam_active_polls !== 'undefined') {
            Object.keys(live_cam_active_polls).forEach(uid => {
                live_cam_stop_polling(uid);
            });
        }

        if (lc_local_tracks.videoTrack) {
            lc_local_tracks.videoTrack.stop();
            lc_local_tracks.videoTrack.close();
            lc_local_tracks.videoTrack = null;
        }
        if (lc_local_tracks.audioTrack) {
            lc_local_tracks.audioTrack.stop();
            lc_local_tracks.audioTrack.close();
            lc_local_tracks.audioTrack = null;
        }
        if (lc_local_tracks.screenTrack) {
            lc_local_tracks.screenTrack.stop();
            lc_local_tracks.screenTrack.close();
            lc_local_tracks.screenTrack = null;
        }

        if (live_cam_group_id) {
            let base_params = new URLSearchParams();
            base_params.append('group_id', live_cam_group_id);
            if (user_csrf_token) base_params.append('csrf_token', user_csrf_token);
            if (user_login_session_id) {
                base_params.append('login_session_id', user_login_session_id);
                base_params.append('access_code', user_access_code);
                base_params.append('session_time_stamp', user_session_time_stamp);
            }

            fetch(api_request_url, {
                method: 'POST',
                body: new URLSearchParams([...base_params, ['remove', 'live_camera']]),
                keepalive: true
            });

            fetch(api_request_url, {
                method: 'POST',
                body: new URLSearchParams([...base_params, ['remove', 'live_camera_viewers'], ['all_targets', 'true']]),
                keepalive: true
            });
        }

        if (live_cam_room) {
            await live_cam_room.leave();
            live_cam_room = null;
        }

        live_cam_group_id = null;
        live_cam_is_active = false;
        current_live_intent = null;
        current_live_privacy = null;

        $('#live_cameras').empty();
        $(".main .chatbox > .header > .icons .toggle_lc_viewers").addClass('d-none');
        $(".live_cam_controls_container").addClass('d-none');
        $(".live_camera_interface, .stop_broad_cast_icon, .resize_live_camera").addClass('d-none');
        $(".start_broad_cast_icon").removeClass('d-none');
        $('.live_cam_btn_broadcast').removeClass('live-active');

    } catch (err) {
        console.error("Error during Agora end_live_camera:", err);
    } finally {
        setTimeout(() => {
            window.is_ending_live = false;
        }, 2000);
    }
}

$(document).on('change', '.main .middle > .live_camera_interface > .live_cam_settings_box .select_camera', async function() {
    if (!live_cam_room) {
        console.warn("No active Agora session");
        return;
    }

    const selectedDeviceId = $(this).val();
    if (!selectedDeviceId) return;

    try {
        if (typeof preferredCamId !== 'undefined') preferredCamId = selectedDeviceId;

        if (lc_local_tracks.screenTrack) {
            console.log("Screen sharing active, camera source not switched in background");
            return;
        }

        if (lc_local_tracks.videoTrack) {
            await lc_local_tracks.videoTrack.setDevice(selectedDeviceId);

            live_cam_render_video(
                lc_local_tracks.videoTrack,
                null,
                live_cam_room.uid,
                true,
                current_live_privacy === 'private',
                true
            );
        } else {
            lc_local_tracks.videoTrack = await AgoraRTC.createCameraVideoTrack({
                cameraId: selectedDeviceId
            });
            await live_cam_room.publish(lc_local_tracks.videoTrack);

            live_cam_render_video(
                lc_local_tracks.videoTrack,
                null,
                live_cam_room.uid,
                true,
                current_live_privacy === 'private',
                true
            );
        }

    } catch (error) {
        console.error("Agora: Failed to switch camera:", error);
    }
});

$(document).on('change', '.main .middle > .live_camera_interface > .live_cam_settings_box .select_microphone', async function() {
    if (!live_cam_room) {
        console.warn("No active Agora session");
        return;
    }

    const selectedDeviceId = $(this).val();
    if (!selectedDeviceId) return;

    try {
        if (typeof preferredMicId !== 'undefined') preferredMicId = selectedDeviceId;

        if (lc_local_tracks.audioTrack) {
            await lc_local_tracks.audioTrack.setDevice(selectedDeviceId);
        } else {
            lc_local_tracks.audioTrack = await AgoraRTC.createMicrophoneAudioTrack({
                microphoneId: selectedDeviceId,
                encoderConfig: "high_quality"
            });
            await live_cam_room.publish(lc_local_tracks.audioTrack);
        }

        const $micBtn = $('#live_cam_toggle_mic');
        if (!lc_local_tracks.audioTrack.muted) {
            $micBtn.removeClass('live_cam_off');
            $micBtn.find('i').removeClass('bi-mic-mute-fill').addClass('bi-mic-fill');
        }

    } catch (error) {
        console.error("Agora: Failed to switch microphone:", error);
    }
});

$(document).on('click', '#live_cam_hide_chat', function() {
    $('.main .middle > .live_camera_interface').toggleClass('full_height');
});

$(document).on('click', '#live_cam_toggle_mic', async function() {
    if (!lc_local_tracks.audioTrack) return;

    const $btn = $(this);
    const isMuted = lc_local_tracks.audioTrack.muted;

    try {
        await lc_local_tracks.audioTrack.setMuted(!isMuted);

        if (isMuted) {
            $btn.removeClass('live_cam_off');
            $btn.find('i').removeClass('bi-mic-mute-fill').addClass('bi-mic-fill');
        } else {
            $btn.addClass('live_cam_off');
            $btn.find('i').removeClass('bi-mic-fill').addClass('bi-mic-mute-fill');
        }
    } catch (error) {
        console.error("Agora: Failed to toggle microphone:", error);
    }
});

$(document).on('click', '#live_cam_toggle_video', async function() {
    if (!lc_local_tracks.videoTrack) return;

    const $btn = $(this);
    const isMuted = lc_local_tracks.videoTrack.muted;

    try {
        await lc_local_tracks.videoTrack.setMuted(!isMuted);

        if (isMuted) {
            $btn.removeClass('live_cam_off');
            $btn.find('i').removeClass('bi-camera-video-off-fill').addClass('bi-camera-video-fill');
        } else {
            $btn.addClass('live_cam_off');
            $btn.find('i').removeClass('bi-camera-video-fill').addClass('bi-camera-video-off-fill');
        }
    } catch (error) {
        console.error("Agora: Failed to toggle camera:", error);
    }
});