var live_kit_video_client = null;
var isVideoChatActive = false;
video_chat_available = true;
var localTracks = [];
var localParticipantContainer;
var audio_only_chat = false;
var preferredMicId;
var preferredCamId;
var currentCamId;
var currentMicId;
var mic_mute_on_load = false;
let psh_tkLastTapTime = 0;
let psh_tkToggleState = false;
var mute_users_during_call = false;

var videochat_GridContainer = $('#video-chat-grid');
var video_chat_formData = new FormData();

const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);


$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .toggle_chat_window", function(e) {
    $('.main .middle > .video_chat_interface').toggleClass('show_chat_window');
});

$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .leave_video_call", function(e) {
    if (isVideoChatActive) {
        exit_video_chat();
    }
});

$("body").on("click", ".main .chatbox .join_video_call", function(e) {

    $('.main .middle > .mini_game_window').addClass('d-none');
    $('.main .middle > .mini_game_window > .mini_game_embed > div').html('');

    if ($('.call_notification').length > 0) {
        if (!$('.call_notification .call_ringtone')[0].paused) {
            $('.call_notification .call_ringtone')[0].pause();
            $('.call_notification .call_ringtone')[0].currentTime = 0;
        }
    }

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

    $('.main .video_chat_container>.icons>span.toggle_video_call_mic').show();
    $('.main .video_chat_container>.icons>span.join_video_chat_now').hide();
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

    if ($(this).attr('audio_only') !== undefined && $(this).attr('audio_only') === 'yes') {
        video_chat_formData.append('audio_only', true);
        $('.main .video_chat_container>.icons>span.toggle_video_call_camera').hide();
        $('.main .video_chat_container>.icons>span.toggle_screen_share').hide();
    } else {
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

    if ($(this).attr('audio_only') !== undefined && $(this).attr('audio_only') === 'yes') {
        $('.main .middle > .video_chat_interface').addClass('audio_only_chat');
        audio_only_chat = true;
    } else {
        audio_only_chat = false;
        $('.main .middle > .video_chat_interface').removeClass('audio_only_chat');
    }

    if (isVideoChatActive) {
        exit_video_chat();
    } else {

        if ($('.main .chatbox').attr('user_id') !== undefined) {
            current_video_caller_id = $('.main .chatbox').attr('user_id');
        }

        initilazing_video_chat();
        create_video_chat();
    }
});

$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .toggle_video_call_camera", function(e) {

    if (!live_kit_video_client) {
        return;
    }

    if (!isVideoChatActive) {
        return;
    }

    localTracks.forEach(function (track) {
        if (track.kind === 'video') {

            if (track.isMuted) {
                track.unmute();
            } else {
                track.mute();
            }
        }
    });


    if ($(this).find('.cam_disabled').hasClass('d-none')) {
        live_kit_video_client.localParticipant.setCameraEnabled(false);
    } else {
        live_kit_video_client.localParticipant.setCameraEnabled(true);
    }

    if ($(this).find('.cam_disabled').hasClass('d-none')) {
        $(this).find('.cam_not_disabled').addClass('d-none');
        $(this).find('.cam_disabled').removeClass('d-none');
    } else {
        $(this).find('.cam_disabled').addClass('d-none');
        $(this).find('.cam_not_disabled').removeClass('d-none');
    }

});

$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .toggle_video_call_mic", function(e) {
    if (!live_kit_video_client || !isVideoChatActive) return;

    const participant = live_kit_video_client.localParticipant;
    const enabled = !participant.isMicrophoneEnabled;

    participant.setMicrophoneEnabled(enabled);

    $(this).find('.mic_not_muted').toggleClass('d-none', !enabled);
    $(this).find('.mic_muted').toggleClass('d-none', enabled);

});

$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .toggle_screen_share", function(e) {

    if (!live_kit_video_client) {
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
        $('.main .video_chat_container>.icons>span.toggle_video_call_camera').hide();
        $(this).find('.stop_screen_share ').removeClass('d-none');
    }

});


function exit_video_chat() {

    total_vc_users = live_kit_video_client?.remoteParticipants?.size || 0;

    $('.main .middle > .video_chat_interface').addClass('d-none');

    $('.toggle_push_to_talk').removeClass('active');
    $('.toggle_push_to_talk').addClass('d-none');

    $('.toggle_video_call_mic .mic_muted').addClass('d-none');
    $('.toggle_video_call_mic .mic_not_muted').removeClass('d-none');

    $('.toggle_screen_share .stop_screen_share').addClass('d-none');
    $('.toggle_screen_share .share_user_screen').removeClass('d-none');

    $('.toggle_video_call_camera .cam_disabled').addClass('d-none');
    $('.toggle_video_call_camera .cam_not_disabled').removeClass('d-none');

    leaveChannel();
    stop_update_video_chat_status();
    current_video_chat_type = current_video_chat_id = null;
}

function leaveChannel() {

    if (!isVideoChatActive) {
        return;
    }

    if (live_kit_video_client) {
        live_kit_video_client.disconnect();
        live_kit_video_client = null;
    }
    localTracks.forEach(function (track) {
        track.stop();
    });
    localTracks = [];
    if (localParticipantContainer !== undefined && localParticipantContainer) {
        localParticipantContainer.remove();
    }

    videochat_GridContainer.innerHTML = '';
    $('.video_chat_container > .video_chat_full_view').html('');

    isVideoChatActive = false;
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

$(document).on("click", ".va_volume_control .ios_mute_btn", function() {
    const $icon = $(this);
    const mediaElement = $icon.closest('.participant-container').find('video, audio')[0];

    if (mediaElement) {
        mediaElement.muted = !mediaElement.muted;
        if (mediaElement.muted) {
            $icon.removeClass('bi-volume-up-fill').addClass('bi-volume-mute-fill text-danger');
        } else {
            $icon.removeClass('bi-volume-mute-fill text-danger').addClass('bi-volume-up-fill');
        }
    }
});

$(document).on("input", ".va_volume_control .vol_control", function () {

    var va_uid = $(this).closest(".participant-container").attr("user_identity");
    var volume = Number($(this).val()) / 100;

    var participant = live_kit_video_client.remoteParticipants.get(va_uid);
    if (!participant) return;

    var audioEl = document.querySelector(`audio[data-participant="${va_uid}"]`);
    if (audioEl) {
        audioEl.volume = volume;
    }

    var $icon = $(this).siblings(".vol_icon");

    if (volume === 0) {
        $icon.attr("class", "vol_icon bi bi-volume-mute-fill");
    } else if (volume < 0.5) {
        $icon.attr("class", "vol_icon bi bi-volume-down-fill");
    } else {
        $icon.attr("class", "vol_icon bi bi-volume-up-fill");
    }
});



function add_livekit_participant(participant, track) {

    if (track.kind === 'video' || track.kind === 'audio') {

        var participant_identifier = participant.identity;

        var adjust_vol_element = '<div class="va_volume_control">';

        if (isIOS) {
            adjust_vol_element += '<i class="vol_icon ios_mute_btn bi bi-volume-up-fill"></i>';
        } else {
            adjust_vol_element += '<i class="vol_icon bi bi-volume-up-fill"></i>';
            adjust_vol_element += '<input class="vol_control" type="range" min="0" max="100" value="100">';
        }

        adjust_vol_element += '</div>';

        if ($('.participant-container[user_identity="'+participant_identifier+'"]').length > 0) {

            if (track.kind === 'video' && $('.participant-container[user_identity="'+participant_identifier+'"] > video').length > 0) {
                $('.participant-container[user_identity="'+participant_identifier+'"]').find('video').remove();
            }
            var mediaElement = track.attach();

            if (track.kind === "audio") {
                mediaElement.dataset.participant = participant.identity;
                mediaElement.volume = 1.0;
            }

            $('.participant-container[user_identity="'+participant_identifier+'"]').append(mediaElement);
        } else {

            var participantContainer = $('<div></div>')
            .addClass('participant-container')
            .attr({
                "user_identity": participant_identifier,
                "user_sid": participant.sid
            });

            var mediaElement = track.attach();

            if (track.kind === "audio") {
                mediaElement.dataset.participant = participant.identity;
                mediaElement.volume = 1.0;
            }

            participantContainer.append(mediaElement);

            videochat_GridContainer.append(participantContainer);

            fetch_user_info(participant.identity).then(function (userData) {
                var participantUsername = userData.username;

                var group_attribute = '';

                if ($(".main .chatbox").attr('group_id') !== undefined) {
                    group_attribute = 'data-group_identifier="'+$(".main .chatbox").attr('group_id')+'"';
                }

                $('.participant-container[user_identity="'+participant_identifier+'"]').append(adjust_vol_element+'<span class="mute_symbol"></span><span '+group_attribute+' class="participant_name get_info" user_id="'+participant.identity+'">@' + participantUsername + '</span>');

                if (audio_only_chat) {
                    $('.participant-container[user_identity="'+participant_identifier+'"]').append('<span class="participant_img"><img src="'+userData.image+'"/></span>');
                }
            }).catch(function (error) {
                console.log(error);
                $('.participant-container[user_identity="'+participant_identifier+'"]').append(adjust_vol_element + '<span class="mute_symbol"></span><span class="participant_name">@'+participant.identity+'</span>');
            });

            var vc_options_svg = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 1024 1024"> <path fill="currentColor" d="M983.162 385.156l-111.835-13.828c-1.838-4.702-3.768-9.364-5.784-13.986l69.296-88.855c14.454-18.534 12.824-44.928-3.798-61.546l-114.055-113.991c-16.616-16.608-43.004-18.228-61.526-3.786l-88.823 69.268c-4.624-2.016-9.288-3.948-13.988-5.788l-13.83-111.807c-2.886-23.32-22.696-40.83-46.194-40.83h-161.273c-23.496 0-43.312 17.51-46.194 40.832l-13.828 111.807c-4.698 1.838-9.362 3.768-13.976 5.78l-88.829-69.284c-18.522-14.444-44.916-12.828-61.536 3.788l-114.041 114.017c-16.62 16.614-18.246 43.012-3.792 61.542l69.288 88.833c-2.024 4.624-3.956 9.294-5.8 14.004l-111.811 13.828c-23.318 2.888-40.832 22.698-40.832 46.196v161.279c0 23.5 17.516 43.316 40.842 46.194l111.801 13.806c1.838 4.704 3.768 9.372 5.788 13.988l-69.282 88.833c-14.448 18.528-12.822 44.92 3.788 61.534l114.041 114.061c16.614 16.62 43.014 18.242 61.546 3.792l88.833-69.296c4.612 2.014 9.276 3.948 13.974 5.782l13.828 111.841c2.882 23.32 22.696 40.832 46.194 40.832h161.273c23.496 0 43.31-17.51 46.194-40.832l13.83-111.839c4.702-1.84 9.364-3.77 13.986-5.784l88.849 69.3c18.532 14.45 44.926 12.828 61.542-3.796l114.029-114.061c16.614-16.616 18.234-43.008 3.786-61.536l-69.282-88.817c2.024-4.626 3.956-9.294 5.796-14.004l111.801-13.806c23.322-2.88 40.842-22.696 40.842-46.194v-161.279c-0.006-23.496-17.52-43.306-40.838-46.192zM930.905 551.477l-99.727 12.316c-18.302 2.262-33.544 15.112-38.862 32.772-5.626 18.674-13.12 36.774-22.276 53.806-8.734 16.248-7.050 36.126 4.294 50.67l61.796 79.226-55.848 55.864-79.242-61.81c-14.538-11.336-34.404-13.026-50.654-4.3-17.026 9.144-35.13 16.638-53.81 22.268-17.654 5.322-30.5 20.558-32.762 38.854l-12.338 99.765h-78.984l-12.334-99.765c-2.262-18.292-15.102-33.526-32.75-38.85-18.734-5.65-36.834-13.142-53.798-22.264-16.244-8.738-36.122-7.054-50.672 4.294l-79.23 61.804-55.854-55.87 61.794-79.236c11.342-14.546 13.026-34.412 4.294-50.656-9.136-17.002-16.63-35.108-22.274-53.822-5.324-17.65-20.564-30.494-38.86-32.752l-99.721-12.312v-78.984l99.731-12.334c18.292-2.262 33.526-15.102 38.846-32.75 5.648-18.712 13.144-36.812 22.286-53.8 8.748-16.248 7.066-36.134-4.286-50.684l-61.806-79.24 55.848-55.836 79.24 61.804c14.546 11.344 34.418 13.026 50.67 4.292 16.986-9.13 35.082-16.62 53.792-22.264 17.65-5.324 30.494-20.558 32.756-38.854l12.332-99.733h78.988l12.338 99.737c2.266 18.296 15.114 33.534 32.768 38.854 18.656 5.622 36.758 13.114 53.804 22.27 16.244 8.728 36.11 7.044 50.65-4.298l79.232-61.788 55.854 55.824-61.794 79.236c-11.332 14.534-13.026 34.382-4.316 50.622 9.16 17.080 16.656 35.198 22.276 53.846 5.322 17.65 20.558 30.496 38.854 32.762l99.755 12.338v78.978z"> </path> <path fill="currentColor" d="M511.991 310.304c-111.211 0-201.689 90.487-201.689 201.711 0 111.205 90.477 201.677 201.689 201.677 111.217 0 201.703-90.471 201.703-201.677 0-111.223-90.485-201.711-201.703-201.711zM511.991 620.602c-59.882 0-108.599-48.712-108.599-108.587 0-59.894 48.718-108.621 108.599-108.621 59.888 0 108.611 48.726 108.611 108.621 0 59.876-48.724 108.587-108.611 108.587z"> </path> </svg>';

            var lk_player_options = '<li class="mute_remote_user vc_mute_user" user_id="'+participant.identity+'">'+language_string('mute')+'</li>';
            lk_player_options += '<li class="mute_remote_user vc_unmute_user d-none" unmute="true" user_id="'+participant.identity+'">'+language_string('unmute')+'</li>';
            lk_player_options = '<div class="vc_options"><div class="dropdown_button">'+vc_options_svg+'<ul class="dropdown_list">'+lk_player_options+'</ul></div></div>'
            if (mute_users_during_call) {
                $('.participant-container[user_identity="'+participant_identifier+'"]').append(lk_player_options);
            }
        }
    }
}


async function share_device_screen() {

    try {
        var screen_share_tracks = await live_kit_video_client.localParticipant.createScreenTracks({
            audio: true,
        });

        screen_share_tracks.forEach((track) => {
            live_kit_video_client.localParticipant.publishTrack(track);
        });

        if ($('.main .middle > .video_chat_interface > .video_chat_container').hasClass('full_view_container')) {
            if ($('.video_chat_container.full_view_container > .video_chat_full_view > div').length > 0) {
                $('.video_chat_container.full_view_container > .video_chat_full_view > div').appendTo('.main .middle > .video_chat_interface > .video_chat_container > .video_chat_grid');
            }

            $('.main .middle > .video_chat_interface > .video_chat_container').removeClass('full_view_container');
        }

        localParticipantContainer.find('video').remove();
        var localVideoElement = screen_share_tracks.find(track => track.kind === 'video').attach();
        localParticipantContainer.append(localVideoElement);
    } catch (error) {
        console.error('Error sharing screen share:', error);

        $('.main .video_chat_container .toggle_screen_share .stop_screen_share').addClass('d-none');
        $('.main .video_chat_container .toggle_screen_share .share_user_screen').removeClass('d-none');
        $('.main .video_chat_container>.icons>span.toggle_video_call_camera').show();
    }
}

async function stop_share_device_screen() {

    try {
        live_kit_video_client.localParticipant.setScreenShareEnabled(false);
    } catch (error) {
        console.error('Error stopping screen share:', error);
    }

    if ($('.main .middle > .video_chat_interface > .video_chat_container').hasClass('full_view_container')) {
        if ($('.video_chat_container.full_view_container > .video_chat_full_view > div').length > 0) {
            $('.video_chat_container.full_view_container > .video_chat_full_view > div').appendTo('.main .middle > .video_chat_interface > .video_chat_container > .video_chat_grid');
        }

        $('.main .middle > .video_chat_interface > .video_chat_container').removeClass('full_view_container');
    }

    var cam_permissionGranted = await checkWebcamAndPermission();

    var mic_permissionGranted = await checkMicrophonePermission();

    var local_video_tracks = await live_kit_video_client.localParticipant.createTracks({
        audio: mic_permissionGranted, video: cam_permissionGranted
    });

    local_video_tracks.forEach((track) => {
        live_kit_video_client.localParticipant.publishTrack(track);
    });

    localParticipantContainer.find('video').remove();

    if (cam_permissionGranted) {
        var localVideoElement = local_video_tracks.find(track => track.kind === 'video').attach();
        localParticipantContainer.append(localVideoElement);
    }
}

async function getPreferredCamera() {
    try {
        let devices = await navigator.mediaDevices.enumerateDevices();
        let cameras = devices.filter(d => d.kind === "videoinput");

        if (!cameras.length) {
            console.log("No cameras found!");
            return null;
        }

        const labelsMissing = cameras.every(cam => !cam.label);

        if (labelsMissing) {
            console.log("Camera labels hidden, requesting permission...");
            const tempStream = await navigator.mediaDevices.getUserMedia({
                video: true
            });
            tempStream.getTracks().forEach(track => track.stop());

            devices = await navigator.mediaDevices.enumerateDevices();
            cameras = devices.filter(d => d.kind === "videoinput");
        }

        if (currentCamId) {
            const matchedCam = cameras.find(cam => cam.deviceId === currentCamId);
            if (matchedCam) {
                console.log(`Using currentCamId: ${matchedCam.label}`);
                return matchedCam.deviceId;
            }
        }

        let preferredCam = cameras.find(cam => {
            const label = cam.label.toLowerCase();
            return label.includes("front") || label.includes("default");
        });
        if (!preferredCam) {
            preferredCam = cameras[0];
            console.log("No preferred camera found. Using fallback.");
        }

        console.log(`Selected Camera: ${preferredCam.label}`);
        return preferredCam.deviceId;

    } catch (err) {
        console.error("Error selecting camera:", err);
        return null;
    }
}

$(document).ready(function () {

    $('.toggle_push_to_talk')
    .on('mousedown touchstart', function (e) {
        if (isVideoChatActive && !psh_tkToggleState) {
            $(this).addClass('active');

            if (live_kit_video_client && isVideoChatActive) {
                live_kit_video_client.localParticipant.setMicrophoneEnabled(true);
            }
        }

        // Handle double-tap toggle
        if (e.type === 'touchstart') {
            const psh_tkCurrentTime = new Date().getTime();
            const psh_tkTapLength = psh_tkCurrentTime - psh_tkLastTapTime;

            if (psh_tkTapLength < 300 && psh_tkTapLength > 0) {
                psh_tkToggleState = !psh_tkToggleState;
                $(this).toggleClass('active', psh_tkToggleState);

                if (live_kit_video_client && isVideoChatActive) {
                    live_kit_video_client.localParticipant.setMicrophoneEnabled(psh_tkToggleState);
                }
            }

            psh_tkLastTapTime = psh_tkCurrentTime;
        }
    })
    .on('dblclick', function () {
        if (isVideoChatActive) {
            psh_tkToggleState = !psh_tkToggleState;
            $(this).toggleClass('active', psh_tkToggleState);

            if (live_kit_video_client) {
                live_kit_video_client.localParticipant.setMicrophoneEnabled(psh_tkToggleState);
            }
        }
    })
    .on('mouseup mouseleave touchend touchcancel', function () {
        if (isVideoChatActive && !psh_tkToggleState) {
            $(this).removeClass('active');

            if (live_kit_video_client && isVideoChatActive) {
                live_kit_video_client.localParticipant.setMicrophoneEnabled(false);
            }
        }
    });
});


async function create_video_chat() {
    try {

        var mic_permissionGranted = await checkMicrophonePermission();

        var video_cam_enabled = false;
        audio_only_chat = false;
        var publish_local_media = true;

        var videoChatData = await fetchVideoChatData();

        if (videoChatData.alert_message) {
            alert_message(videoChatData.alert_message);
            exit_video_chat();
            return;
        }

        var {
            token, channel: roomName, live_kit_url, subscriber_only, audio_only
        } = videoChatData;

        if (subscriber_only) {
            hideElementsForSubscriberMode();
            publish_local_media = false;
        }

        if (videoChatData.can_join_later !== undefined) {
            $('.main .video_chat_container>.icons>span.join_video_chat_now').show();
        }

        if (videoChatData.mute_users_during_call !== undefined) {
            mute_users_during_call = true;
        } else {
            mute_users_during_call = false;
        }

        live_kit_video_client = new LivekitClient.Room({
            adaptiveStream: true,
            autoSubscribe: true,
        });

        audio_only_chat = Boolean(audio_only);

        var skip_vc_camera_confirm = false;

        if ($('.main .chatbox .join_video_call').hasClass('join_video_chat_now')) {
            $('.main .chatbox .join_video_call').removeClass('join_video_chat_now');
        } else if (system_variable('view_before_joining_group_vc') === 'enable') {
            skip_vc_camera_confirm = true;
        }

        if (audio_only_chat) {
            var cam_permissionGranted = false;
        } else if (!skip_vc_camera_confirm && current_video_chat_type === 'group' && system_variable('confirm_camera_use') === 'enable') {
            var enableCamera = confirm(language_string('enable_camera_prompt'));
            if (enableCamera) {
                var cam_permissionGranted = await checkWebcamAndPermission();
            } else {
                var cam_permissionGranted = false;
                $('.main .video_chat_container>.icons>span.toggle_video_call_camera').hide();
            }
        } else {
            var cam_permissionGranted = await checkWebcamAndPermission();
        }



        video_cam_enabled = audio_only ? false: cam_permissionGranted;

        await live_kit_video_client.prepareConnection(live_kit_url, token);
        await live_kit_video_client.connect(live_kit_url, token);

        console.log(`Connected to Room: ${roomName}`);

        isVideoChatActive = true;

        update_video_chat_status();

        update_wsocket_data();

        clearCallNotifications();

        live_kit_video_client.remoteParticipants.forEach(participant => {
            participant.trackPublications.forEach(publication => {
                if (publication.track) {
                    add_livekit_participant(participant, publication.track);
                }
            });
        });

        live_kit_video_client.on(LivekitClient.RoomEvent.TrackSubscribed, (track, publication, participant) => {
            add_livekit_participant(participant, track);
        });

        if (publish_local_media) {
            await setupLocalParticipant(
                live_kit_video_client,
                mic_permissionGranted,
                video_cam_enabled,
                audio_only_chat
            );
        }

        if (publish_local_media && live_kit_video_client && isVideoChatActive) {
            if (mic_mute_on_load) {

                if (system_variable('push_to_talk_feature') === 'enable') {
                    const pt_tooltipInstance = bootstrap.Tooltip.getInstance(document.getElementById('push_to_talk_icon'));
                    if (pt_tooltipInstance) {
                        pt_tooltipInstance.show();
                    }
                }

                live_kit_video_client.localParticipant.setMicrophoneEnabled(false);
            } else {
                live_kit_video_client.localParticipant.setMicrophoneEnabled(true);
            }
        }

        live_kit_video_client.on('participantDisconnected', (disconnectedParticipant) => {
            console.log(`Participant ${disconnectedParticipant.identity} has disconnected`);
            removeDisconnectedParticipant(disconnectedParticipant.sid);
        });



        live_kit_video_client.on('trackMuted', (track, publication) => {
            const id = publication.participantInfo.identity;
            const $container = $(`.participant-container[user_identity="${id}"]`);
            if (track.kind === 'audio') {
                $container.find('.mute_symbol').addClass('muted');
            }
        });

        live_kit_video_client.on('trackUnmuted', (track, publication) => {
            var participant_identifier = publication.participantInfo.identity;
            $('.participant-container[user_identity="'+participant_identifier+'"]').find('span.mute_symbol').removeClass('muted');
            $('.participant-container[user_identity="'+participant_identifier+'"]').removeClass('muted');
            console.log(`unmuted their ${track.kind} track`);
        });

        live_kit_video_client.on('activeSpeakersChanged', (speakers) => {
            $('.video_chat_grid > div').removeClass('talking');

            speakers.forEach((speaker) => {
                const participantId = speaker.sid;
                if (current_logged_user_id && current_logged_user_id == speaker.identity) {
                    $('.video_chat_grid > div.local-participant-window').addClass('talking');
                } else {
                    $(`.video_chat_grid > div[user_sid='${participantId}']`).addClass('talking');
                }
            });
        });

    } catch (error) {
        console.error('Error during video chat initialization:', error);
        exit_video_chat();
    }
}

$("body").on("click", ".main .middle > .video_chat_interface > .video_chat_container .join_video_chat_now", function(e) {

    if (!live_kit_video_client) {
        return;
    }

    if (!isVideoChatActive) {
        return;
    }

    exit_video_chat();

    if ($(".main .chatbox").attr('group_id') !== undefined && $('.main .middle>.video_chat_interface').attr('group_id') !== undefined) {
        if ($(".main .chatbox").attr('group_id') != $('.main .middle>.video_chat_interface').attr('group_id')) {
            alert_message(language_string('open_chat_to_join_vc'));
            return;
        }
    } else {
        alert_message(language_string('open_chat_to_join_vc'));
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

function vc_remote_mute_user(action, group_id) {

    if (!live_kit_video_client) {
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
            live_kit_video_client.localParticipant.setMicrophoneEnabled(true);
            $('.main .video_chat_container>.icons>span.toggle_video_call_mic').show();
        }
    } else if (action == 'mute_remote_user') {

        live_kit_video_client.localParticipant.setMicrophoneEnabled(false);

        $('.main .video_chat_container>.icons>span.toggle_push_to_talk').removeClass('active');
        $('.main .video_chat_container>.icons>span.toggle_video_call_mic').hide();
        $('.main .video_chat_container>.icons>span.toggle_push_to_talk').addClass('d-none');
    }
}



async function fetchVideoChatData() {
    try {

        if ($('.main .chatbox .join_video_call').hasClass('join_video_chat_now')) {
            video_chat_formData.append('join_video_chat_now', true);
        }

        var response = await fetch(api_request_url, {
            method: 'POST',
            body: video_chat_formData,
        });

        if (!response.ok) {
            console.error(`Error: ${response.status} - ${response.statusText}`);
            throw new Error(`Failed to fetch video chat data. Status: ${response.status}`);
        }

        var data = await response.json();
        return data;

    } catch (error) {
        console.error('Error fetching video chat data:', error);
        throw error;
    }
}

function hideElementsForSubscriberMode() {
    $('.main .video_chat_container>.icons>span.toggle_video_call_camera').hide();
    $('.main .video_chat_container>.icons>span.toggle_screen_share').hide();
    $('.main .video_chat_container>.icons>span.toggle_video_call_mic').hide();

    $('.toggle_push_to_talk').addClass('d-none');
}

function clearCallNotifications() {
    if (call_notification_timeout_id) {
        clearTimeout(call_notification_timeout_id);
    }
    $('.call_notification').attr('current_call_id', 0);
}

async function getPreferredMicrophone() {
    try {
        let devices = await navigator.mediaDevices.enumerateDevices();
        let microphones = devices.filter(d => d.kind === "audioinput");

        if (!microphones.length) {
            console.log("No microphones found!");
            return null;
        }

        const labelsMissing = microphones.every(m => !m.label);

        if (labelsMissing) {
            console.log("Mic labels hidden. Requesting permission...");

            const tempStream = await navigator.mediaDevices.getUserMedia({
                audio: true
            });
            tempStream.getTracks().forEach(track => track.stop());

            devices = await navigator.mediaDevices.enumerateDevices();
            microphones = devices.filter(d => d.kind === "audioinput");
        }

        if (currentMicId) {
            const matched = microphones.find(m => m.deviceId === currentMicId);
            if (matched) return matched.deviceId;
        }

        const preferred = microphones.find(m =>
            m.label.toLowerCase().includes("communications")
        );

        return (preferred || microphones[0]).deviceId;

    } catch (err) {
        console.error("Microphone error:", err);
        return null;
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

$("body").on('change', '.video_chat_settings_box .card .select_camera', async function (e) {
    const selectedCamId = $(this).val();

    if (!live_kit_video_client || !isVideoChatActive) {
        currentCamId = selectedCamId;
        return;
    }

    try {
        console.log("Switching camera to:", selectedCamId);

        const trackPub = live_kit_video_client.localParticipant.getTrackPublication(LivekitClient.Track.Source.Camera);

        if (trackPub && trackPub.track) {
            await live_kit_video_client.localParticipant.unpublishTrack(trackPub.track, true);
        }

        await live_kit_video_client.localParticipant.setCameraEnabled(true, {
            deviceId: selectedCamId
        });

        const newTrackPub = live_kit_video_client.localParticipant.getTrackPublication(LivekitClient.Track.Source.Camera);
        if (newTrackPub && newTrackPub.videoTrack) {
            const container = $('.participant-container.local-participant-window');

            container.find('video').each(function() {
                this.pause();
                this.srcObject = null;
                $(this).remove();
            });

            const newVideoEl = newTrackPub.videoTrack.attach();
            container.append(newVideoEl);
        }

        currentCamId = selectedCamId;
    } catch (error) {
        console.error("Camera switch failed:", error);
    }
});

$("body").on('change', '.video_chat_settings_box .card .select_microphone', async function(e) {
    const selectedMicId = $(this).val();

    if (!live_kit_video_client || !isVideoChatActive) {
        currentMicId = selectedMicId;
        return;
    }

    try {
        console.log("Switching microphone to:", selectedMicId);

        const trackPub = live_kit_video_client.localParticipant.getTrackPublication(LivekitClient.Track.Source.Microphone);
        if (trackPub && trackPub.track) {
            await live_kit_video_client.localParticipant.unpublishTrack(trackPub.track, true);
        }

        await live_kit_video_client.localParticipant.setMicrophoneEnabled(true, {
            deviceId: selectedMicId
        });

        currentMicId = selectedMicId;
    } catch (error) {
        console.error("Microphone switch failed:", error);
    }
});


async function setupLocalParticipant(client, micEnabled, videoEnabled, audioOnly) {
    try {

        if (micEnabled) {
            preferredMicId = await getPreferredMicrophone();
            await get_mic_list('videochat');
        }

        if (videoEnabled) {
            preferredCamId = await getPreferredCamera();
            await get_cam_list('videochat');
        } else {
            $('.video_chat_settings_box .card .select_camera_box').hide();
        }

        localTracks = await LivekitClient.createLocalTracks({
            audio: micEnabled ? {
                noiseSuppression: true,
                echoCancellation: true,
                autoGainControl: true,
                deviceId: preferredMicId
            }: false,
            video: videoEnabled ? {
                deviceId: preferredCamId
            }: false
        });
        localParticipantContainer = $('<div></div>').addClass('participant-container');

        if (videoEnabled) {
            var localVideoElement = localTracks.find(track => track.kind === 'video').attach();
            localParticipantContainer.append(localVideoElement);
        } else if (audioOnly) {
            localParticipantContainer.append(
                `<span class="participant_img"><img src="${$('.logged_in_user_avatar').attr('src')}" /></span>`
            );
        }

        var groupAttr = $(".main .chatbox").attr('group_id')
        ? `data-group_identifier="${$(".main .chatbox").attr('group_id')}"`: '';

        localParticipantContainer
        .addClass('identity You local-participant-window')
        .append(`<span ${groupAttr} class="participant_name get_info" user_id="${$('.logged_in_user_id').text()}">You</span>`);

        client.localParticipant.setMicrophoneEnabled(micEnabled);
        client.localParticipant.setCameraEnabled(videoEnabled);

        videochat_GridContainer.append(localParticipantContainer);
    } catch (error) {
        console.error('Error setting up local participant:', error);
        throw error;
    }
}

function removeDisconnectedParticipant(participantSid) {
    $('.participant-container').each(function () {
        if ($(this).attr('user_sid') === participantSid) {
            if ($(this).parent().hasClass('video_chat_full_view')) {
                $('.main .middle > .video_chat_interface > .video_chat_container').removeClass('full_view_container');
            }

            $(this).remove();
        }
    });

    var total_vclients = $('.main .middle > .video_chat_interface > .video_chat_container > .video_chat_grid > div').length;

    if (total_vclients === 0 && $('.main .middle > .video_chat_interface > .video_chat_container').hasClass('full_view_container')) {
        if ($('.video_chat_container.full_view_container > .video_chat_full_view > div').length > 0) {
            $('.video_chat_container.full_view_container > .video_chat_full_view > div').appendTo('.main .middle > .video_chat_interface > .video_chat_container > .video_chat_grid');
        }

        $('.main .middle > .video_chat_interface > .video_chat_container').removeClass('full_view_container');
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

    if (live_cam_room && live_cam_room.state === 'connected') {
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

        live_cam_room = new LivekitClient.Room({
            adaptiveStream: true,
            autoSubscribe: false,
        });

        live_cam_room.on(LivekitClient.RoomEvent.TrackPublished, async (publication, participant) => {
            const identity = participant.identity;
            const $existingBox = $(`#live_cam_box_${identity}`);

            if (publication.kind === 'video' && $existingBox.length > 0) {
                await publication.setSubscribed(true);

                let timeout = 0;
                while (!publication.videoTrack && timeout < 50) {
                    await new Promise(r => setTimeout(r, 100));
                    timeout++;
                }

                if (publication.videoTrack) {
                    live_cam_render_video(publication.videoTrack, null, identity, false, false, true);
                    console.log(`Auto-switched video source for ${identity}`);
                }
            }

            if (publication.kind === 'audio' && $existingBox.length > 0) {
                await publication.setSubscribed(true);
            }
        });

        live_cam_room.on(LivekitClient.RoomEvent.TrackSubscribed, (track, publication, participant) => {
            const identity = participant.identity;
            const $existingBox = $(`#live_cam_box_${identity}`);

            if (track.kind === 'video') {
                if ($existingBox.length > 0) {
                    live_cam_render_video(track, null, identity, false, false, true);
                    console.log(`Video track (${publication.source}) attached for ${identity}`);
                }
            }

            if (track.kind === 'audio') {
                if ($existingBox.length > 0) {
                    const audioEl = track.attach();
                    $(audioEl).attr('id', `audio_${identity}`);

                    const $audioTarget = $existingBox.find('.live-cam-audio-target');
                    $audioTarget.empty().append(audioEl);

                    const $slider = $existingBox.find('.live_cam_volume_slider');
                    if ($slider.length > 0) {
                        const currentVolume = parseFloat($slider.val());
                        audioEl.volume = currentVolume;

                        if (typeof track.setVolume === 'function') {
                            track.setVolume(currentVolume);
                        }
                    }

                    audioEl.play().catch(err => {
                        console.warn("Autoplay blocked for audio. User must interact with the page.");
                    });
                }
            }
        });

        live_cam_room.localParticipant.on(LivekitClient.ParticipantEvent.LocalTrackUnpublished, (publication) => {
            if (publication.source === LivekitClient.Track.Source.ScreenShare) {
                console.log("Local screen share stopped (Event detected on LocalParticipant)");

                $('#live_cam_share_screen').removeClass('live_cam_off')
                .find('i').removeClass('bi-x-circle-fill').addClass('bi-display');

                live_cam_room.localParticipant.setCameraEnabled(true).then(() => {
                    $('#live_cam_toggle_video').removeClass('live_cam_off')
                    .find('i').removeClass('bi-camera-video-off-fill').addClass('bi-camera-video-fill');

                    const interval = setInterval(() => {
                        const camTrack = live_cam_room.localParticipant.getTrackPublication(LivekitClient.Track.Source.Camera)?.videoTrack;
                        if (camTrack) {
                            live_cam_render_video(camTrack, null, live_cam_room.localParticipant.identity, true, false, true);
                            clearInterval(interval);
                        }
                    }, 200);

                    setTimeout(() => clearInterval(interval), 1500);
                });
            }
        });

        live_cam_room.on(LivekitClient.RoomEvent.TrackUnpublished, (pub, participant) => {

            if (participant.identity !== live_cam_room.localParticipant.identity) {
                return;
            }

            const $participantBox = $(`#live_cam_box_${participant.identity}`);

            if ($participantBox.length === 0) {
                return;
            }

            setTimeout(() => {
                const publications = participant.getTrackPublications();
                const hasVideoSource = publications.some(p => p.kind === 'video');

                if (!hasVideoSource) {
                    console.log(`User ${participant.identity} stopped all video. Removing UI.`);

                    const $audioEl = $(`#audio_${participant.identity}`);

                    if ($audioEl.length > 0) {
                        $audioEl.each(function() {
                            this.pause();
                            this.srcObject = null;
                        });
                    }

                    $(`#live_cam_box_${participant.identity}`).remove();
                    $(`#audio_${participant.identity}`).remove();

                    if ($('#live_cameras').children().length === 0) {
                        $(".live_camera_interface").addClass('d-none');
                        $('.main .chatbox .resize_live_camera').addClass('d-none');
                    }
                } else {
                    console.log("Source switch detected. Finding new video track...");

                    const videoPub = publications.find(p => p.kind === 'video' && p.source !== 'screen_share');

                    if (videoPub) {

                        videoPub.setSubscribed(true);

                        let timeout = 0;
                        const checkTrack = setInterval(() => {
                            if (videoPub.videoTrack) {
                                clearInterval(checkTrack);

                                live_cam_render_video(
                                    videoPub.videoTrack,
                                    null,
                                    participant.identity,
                                    false,
                                    false,
                                    true
                                );
                            }
                            timeout++;
                            if (timeout > 100) {
                                clearInterval(checkTrack);
                                console.error("Timeout waiting for new video track");
                            }
                        }, 100);
                    }
                }
            }, 1000);
        });
        live_cam_room.on(LivekitClient.RoomEvent.ParticipantDisconnected, (participant) => {

            var remove_data = {
                remove: 'live_camera_viewers',
                user_id: participant.identity,
                group_id: live_cam_group_id
            };

            if (user_csrf_token !== null) remove_data["csrf_token"] = user_csrf_token;
            if (user_login_session_id !== null) {
                remove_data["login_session_id"] = user_login_session_id;
                remove_data["access_code"] = user_access_code;
                remove_data["session_time_stamp"] = user_session_time_stamp;
            }

            $.post(api_request_url, remove_data);

            const $audioEl = $(`#audio_${participant.identity}`);
            $audioEl.each(function() {
                this.pause();
                this.srcObject = null;
            });
            $audioEl.remove();

            $(`#live_cam_box_${participant.identity}`).remove();
            $(`#audio_${participant.identity}`).remove();

            setTimeout(() => {
                if ($('#live_cameras').children().length === 0) {
                    $(".live_camera_interface").addClass('d-none');
                    $('.main .chatbox .resize_live_camera').addClass('d-none');
                }
            }, 100);
        });

        await live_cam_room.connect(live_cam_data.live_kit_url, live_cam_data.token);
        console.log("LiveCam Connection Successful");
        return true;

    } catch (error) {
        console.error("LiveCam Connection Failed:", error);
        live_cam_group_id = null;
        return false;
    }
}

const checkPendingPrivateRequests = (groupId) => {

    var post_data = {
        load: 'pending_live_cam_viewers',
        group_id: groupId
    };

    if (user_csrf_token !== null) {
        post_data["csrf_token"] = user_csrf_token;
    }

    if (user_login_session_id !== null && user_access_code !== null && user_session_time_stamp !== null) {
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

                var post_data = {
                    update: 'live_camera_viewers',
                    user_id: response.user_id,
                    status: decision,
                    group_id: groupId
                };

                if (user_csrf_token !== null) {
                    post_data["csrf_token"] = user_csrf_token;
                }

                if (user_login_session_id !== null && user_access_code !== null && user_session_time_stamp !== null) {
                    post_data["login_session_id"] = user_login_session_id;
                    post_data["access_code"] = user_access_code;
                    post_data["session_time_stamp"] = user_session_time_stamp;
                }

                $.post(api_request_url, post_data, function() {
                    startPrivatePolling(groupId);
                });
            });
        }
    });
};

const startPrivatePolling = (groupId) => {
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
            const local = live_cam_room.localParticipant;
            const tracks = [...Array.from(local.videoTrackPublications.values()), ...Array.from(local.audioTrackPublications.values())];

            for (const pub of tracks) {
                if (pub.track) await local.unpublishTrack(pub.track, true);
            }

            await local.setCameraEnabled(false);
            await local.setMicrophoneEnabled(false);

            stopPrivatePolling();

            var post_data = {
                remove: 'live_camera', group_id: live_cam_group_id
            };

            if (user_csrf_token !== null) {
                post_data["csrf_token"] = user_csrf_token;
            }

            if (user_login_session_id !== null && user_access_code !== null && user_session_time_stamp !== null) {
                post_data["login_session_id"] = user_login_session_id;
                post_data["access_code"] = user_access_code;
                post_data["session_time_stamp"] = user_session_time_stamp;
            }

            $.post(api_request_url, post_data);

            $(`#live_cam_box_${local.identity}`).remove();
            if ($('#live_cameras').children().length === 0) {
                $('.main .chatbox .resize_live_camera').addClass('d-none');
                $(".live_camera_interface").addClass('d-none');
            }

            $(".start_broad_cast_icon").removeClass('d-none');
            $(".stop_broad_cast_icon").addClass('d-none');
            $('.live_cam_btn_broadcast').removeClass('live-active');
            $(".live_cam_controls_container").addClass('d-none');

            if (live_cam_room) {
                await live_cam_room.disconnect();
                live_cam_room = null;
                console.log("✅ Disconnected successfully");
            }


            current_live_intent = null;
            current_live_privacy = null;
            live_cam_group_id = null;
            $(".main .chatbox > .header > .icons .toggle_lc_viewers").addClass('d-none');

        } catch (err) {
            console.error("Error stopping broadcast:", err);
        }
    } else {
        const isConnected = await live_cam_init_connection('broadcast', broadcastType);
        if (!isConnected) return;

        const local = live_cam_room.localParticipant;

        try {
            const micId = await getPreferredMicrophone();
            await get_mic_list('livecam');

            const camId = await getPreferredCamera();
            await get_cam_list('livecam');

            try {
                if (camId) {
                    await local.setCameraEnabled(true, {
                        videoCaptureOptions: {
                            deviceId: {
                                exact: camId
                            }
                        }
                    });
                } else {
                    await local.setCameraEnabled(true);
                }
            } catch (error) {
                console.error("Failed with preferred camera, trying default:", error);
                await local.setCameraEnabled(true);
            }

            try {
                if (micId) {
                    await local.setMicrophoneEnabled(true, {
                        audioCaptureOptions: {
                            deviceId: {
                                exact: micId
                            },
                            echoCancellation: true,
                            noiseSuppression: true,
                            autoGainControl: true
                        }
                    });
                } else {
                    await local.setMicrophoneEnabled(true, {
                        audioCaptureOptions: {
                            echoCancellation: true,
                            noiseSuppression: true,
                            autoGainControl: true
                        }
                    });
                }
            } catch (micError) {
                console.warn("Failed with exact microphone ID, trying fallback:", micError);

                try {
                    await local.setMicrophoneEnabled(true, {
                        audioCaptureOptions: {
                            echoCancellation: true,
                            noiseSuppression: true,
                            autoGainControl: true
                        }
                    });
                } catch (fallbackError) {
                    console.error("All microphone attempts failed:", fallbackError);
                    console.log("Continuing with video-only broadcast");
                    $('#live_cam_toggle_mic')
                    .addClass('live_cam_off')
                    .find('i')
                    .removeClass('bi-mic-fill bi-mic-mute-fill')
                    .addClass('bi-mic-mute-fill');
                }
            }

            const videoPub = local.getTrackPublication(LivekitClient.Track.Source.Camera);
            const videoTrack = videoPub?.videoTrack;

            if (videoTrack) {
                const isPrivate = (broadcastType === 'private');
                live_cam_render_video(videoTrack, null, local.identity, true, isPrivate);

                var post_data = {
                    add: 'live_camera',
                    group_id: live_cam_group_id,
                    privacy: broadcastType,
                    intent: 'broadcast'
                };

                if (user_csrf_token !== null) {
                    post_data["csrf_token"] = user_csrf_token;
                }

                if (user_login_session_id !== null && user_access_code !== null && user_session_time_stamp !== null) {
                    post_data["login_session_id"] = user_login_session_id;
                    post_data["access_code"] = user_access_code;
                    post_data["session_time_stamp"] = user_session_time_stamp;
                }

                $.post(api_request_url, post_data);

                if (isPrivate) {
                    startPrivatePolling(live_cam_group_id);
                }

                const $view_lc_btn = $(".main .chatbox > .header > .icons .toggle_lc_viewers");
                $view_lc_btn.attr('last_lc_viewer_id', 0);
                $view_lc_btn.attr('total_lc_viewers', 0);
                $view_lc_btn.attr('data-group_id', live_cam_group_id);
                $view_lc_btn.data('group_id', live_cam_group_id);
                $view_lc_btn.removeClass('d-none');

                $('.live_cam_controls_container').removeClass('d-none');

                $(".start_broad_cast_icon").addClass('d-none');
                $(".stop_broad_cast_icon").removeClass('d-none');
                $('.live_cam_btn_broadcast').addClass('live-active');
            }
        } catch (error) {
            console.error("Broadcast Start Failed:", error);
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

    const activeVideoCount = $('.live-cam-item').length;
    if (activeVideoCount >= 8) {
        alert_message(language_string('exceeded_max_broadcasts_view'));
        return false;
    }

    try {
        const live_cam_is_connected = await live_cam_init_connection('view', current_live_privacy || 'public');
        if (!live_cam_is_connected) {
            $btn.removeClass('live-cam-processing');
            return;
        }

        const live_cam_participant = live_cam_room.remoteParticipants.get(live_cam_identity);
        if (!live_cam_participant) {

            var post_data = {
                remove: 'live_camera',
                user_id: user_id,
                group_id: group_id
            };


            if (user_csrf_token !== null) {
                post_data["csrf_token"] = user_csrf_token;
            }

            if (user_login_session_id !== null && user_access_code !== null && user_session_time_stamp !== null) {
                post_data["login_session_id"] = user_login_session_id;
                post_data["access_code"] = user_access_code;
                post_data["session_time_stamp"] = user_session_time_stamp;
            }

            const live_cam_response = await $.post(api_request_url, post_data, null, 'json');

            refresh_group_members(group_id);

            $btn.removeClass('live-cam-processing');
            return;
        }

        var post_data = {
            add: 'live_camera_viewers',
            user_id: user_id,
            group_id: group_id
        };


        if (user_csrf_token !== null) {
            post_data["csrf_token"] = user_csrf_token;
        }

        if (user_login_session_id !== null && user_access_code !== null && user_session_time_stamp !== null) {
            post_data["login_session_id"] = user_login_session_id;
            post_data["access_code"] = user_access_code;
            post_data["session_time_stamp"] = user_session_time_stamp;
        }

        const live_cam_response = await $.post(api_request_url, post_data, null, 'json');

        if (live_cam_response.status === 'approved') {
            $btn.removeClass('live-cam-processing');
            live_cam_initiate_subscription(live_cam_participant);
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
        console.error("Post request failed:", live_cam_error);
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
    const publications = live_cam_participant.getTrackPublications();

    let live_cam_video_pub = publications.find(p => p.source === LivekitClient.Track.Source.ScreenShare);

    if (!live_cam_video_pub) {
        live_cam_video_pub = publications.find(p => p.kind === 'video');
    }

    const live_cam_audio_pub = live_cam_participant.getTrackPublication(LivekitClient.Track.Source.Microphone);

    if (!live_cam_video_pub) {
        console.warn(`User ${live_cam_participant.identity} has no video tracks available.`);
        return false;
    }

    await live_cam_video_pub.setSubscribed(true);
    if (live_cam_audio_pub) await live_cam_audio_pub.setSubscribed(true);

    let timeout = 0;
    while (!live_cam_video_pub.track && timeout < 50) {
        await new Promise(resolve => setTimeout(resolve, 100));
        timeout++;
    }

    if (live_cam_video_pub.track) {
        live_cam_render_video(
            live_cam_video_pub.track,
            live_cam_audio_pub?.track,
            live_cam_participant.identity,
            false,
            false,
            true
        );
    } else {
        console.error("Video track failed to arrive after subscription.");
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

            if (user_csrf_token !== null) {
                post_data["csrf_token"] = user_csrf_token;
            }

            if (user_login_session_id !== null && user_access_code !== null && user_session_time_stamp !== null) {
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
            console.error("Polling error, stopping:", e);
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

        console.log('Replacing Video');

        $videoTarget.find('video').each(function() {
            this.pause();
            this.srcObject = null;
            $(this).remove();
        });
        $videoTarget.empty();

        if (videoTrack) {
            const videoEl = videoTrack.attach();
            $videoTarget.append(videoEl);

            videoEl.play().catch(e => console.warn("Playback failed on switch:", e));
            console.log(`New video track attached for ${identity}`);
        }

        if (audioTrack && !isLocal) {
            const $audioTarget = $box.find('.live-cam-audio-target');
            $audioTarget.find('audio').each(function() {
                this.pause();
                this.srcObject = null;
                $(this).remove();
            });
            const audioEl = audioTrack.attach();
            $(audioEl).attr('id', `audio_${identity}`);
            $audioTarget.append(audioEl);
            audioEl.play().catch(e => console.warn("Audio autoplay blocked on switch"));
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
            console.error("Could not fetch user info:", error);
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
    <input type="range" class="live_cam_volume_slider"
    data-id="${identity}" min="0" max="1" step="0.05" value="1"
    style="width: 80px; height: 4px;">
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
    <div class="live-cam-audio-target" style="display:none !important;"></div>
    </div>`;

    $('#live_cameras').append(live_cam_html);

    if (videoTrack) {
        const videoEl = videoTrack.attach();
        $(`#live_cam_box_${identity} .live-cam-video-target`).append(videoEl);
    }

    if (audioTrack && !isLocal) {
        const audioEl = audioTrack.attach();
        $(audioEl).attr('id', `audio_${identity}`);
        $(`#live_cam_box_${identity} .live-cam-audio-target`).append(audioEl);

        const $existingSlider = $(`.live_cam_volume_slider[data-id="${identity}"]`);

        if ($existingSlider.length > 0) {
            const currentVolume = parseFloat($existingSlider.val());
            audioEl.volume = currentVolume;

            if (typeof audioTrack.setVolume === 'function') {
                audioTrack.setVolume(currentVolume);
            }

            console.log(`Applied saved volume ${currentVolume} to new audio track for ${identity}`);
        }

        audioEl.play().catch(error => {
            if (error.name === 'NotAllowedError') {

                audioEl.muted = true;
                audioEl.play();

                const $box = $(`#live_cam_box_${identity}`);
                $box.find('.live_cam_volume_toggle i')
                .removeClass('bi-volume-up')
                .addClass('bi-volume-mute text-danger');

                $box.find('.live_cam_volume_slider').val(0);
            }
        });
    }
}


$(document).on('click', '#live_cam_share_screen', async function() {
    if (!live_cam_room || !live_cam_room.localParticipant) return;

    const local = live_cam_room.localParticipant;
    const $btn = $(this);
    const $camBtn = $('#live_cam_toggle_video');
    const isSharing = local.isScreenShareEnabled;
    $('#live_cam_open_settings').removeClass('d-none');

    try {
        if (!isSharing) {
            if (local.isCameraEnabled) {
                await local.setCameraEnabled(false);
            }

            await local.setScreenShareEnabled(true, {
                audio: true
            });

            let screenTrack = null;
            let screenAudio = null;
            let retries = 0;
            while (!screenTrack && retries < 30) {
                const pub = local.getTrackPublication(LivekitClient.Track.Source.ScreenShare);
                if (pub && pub.videoTrack) {
                    screenTrack = pub.videoTrack;
                    screenAudio = pub.audioTrack;
                } else {
                    await new Promise(r => setTimeout(r, 100));
                    retries++;
                }
            }

            if (screenTrack) {
                $('#live_cam_open_settings').addClass('d-none');
                $btn.addClass('live_cam_off').find('i').removeClass('bi-display').addClass('bi-x-circle-fill');
                $camBtn.addClass('live_cam_off').find('i').removeClass('bi-camera-video-fill').addClass('bi-camera-video-off-fill');

                live_cam_render_video(screenTrack, screenAudio, local.identity, true, false, true);
            } else {
                console.error("Screen track failed to initialize in time.");
                alert_message("Could not start screen share. Please try again.");
            }

        } else {
            await local.setScreenShareEnabled(false);
            $btn.removeClass('live_cam_off').find('i').removeClass('bi-x-circle-fill').addClass('bi-display');

            const $cameraSelect = $('.main .middle > .live_camera_interface > .live_cam_settings_box .select_camera');

            if ($cameraSelect.length && $cameraSelect.val()) {
                $cameraSelect.trigger('change');
            } else {
                await local.setCameraEnabled(true);
                $camBtn.removeClass('live_cam_off').find('i').removeClass('bi-camera-video-off-fill').addClass('bi-camera-video-fill');

                let camTrack = null;
                let retries = 0;
                while (!camTrack && retries < 30) {
                    const camPub = local.getTrackPublication(LivekitClient.Track.Source.Camera);
                    if (camPub && camPub.videoTrack) {
                        camTrack = camPub.videoTrack;
                    } else {
                        await new Promise(r => setTimeout(r, 100));
                        retries++;
                    }
                }

                if (camTrack) {
                    live_cam_render_video(camTrack, null, local.identity, true, false, true);
                }
            }
        }
    } catch (error) {
        console.error("Screen share error:", error);
    }
});

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
    const identity = $btn.data('id');

    const $audioEl = $(`#audio_${identity}`);
    if ($audioEl.length) {
        const audioEl = $audioEl[0];
        const isMuted = audioEl.muted;

        audioEl.muted = !isMuted;

        if (audioEl.muted) {
            $icon.removeClass('bi-volume-up').addClass('bi-volume-mute text-danger');
        } else {
            $icon.removeClass('bi-volume-mute text-danger').addClass('bi-volume-up');
        }

        if (live_cam_room && identity) {
            const participant = live_cam_room.remoteParticipants.get(String(identity));
            if (participant) {
                const audioPub = participant.getTrackPublication(LivekitClient.Track.Source.Microphone);
                if (audioPub && audioPub.audioTrack && typeof audioPub.audioTrack.setVolume === 'function') {
                    audioPub.audioTrack.setVolume(audioEl.muted ? 0: 1);
                }
            }
        }
    }
});


$(document).on('input', '.live_cam_volume_slider', function() {
    const $this = $(this);
    const identity = $this.data('id');
    const volume = parseFloat($this.val());
    const $icon = $this.closest('.live-cam-volume-wrapper').find('.live_cam_volume_toggle i');

    $icon.removeClass('bi-volume-up bi-volume-down bi-volume-mute text-danger');
    if (volume === 0) {
        $icon.addClass('bi-volume-mute text-danger');
    } else if (volume < 0.5) {
        $icon.addClass('bi-volume-down');
    } else {
        $icon.addClass('bi-volume-up');
    }

    if (live_cam_room && identity) {
        const participant = live_cam_room.remoteParticipants.get(String(identity));

        if (participant) {
            const audioPub = participant.getTrackPublication(LivekitClient.Track.Source.Microphone);

            if (audioPub && audioPub.audioTrack) {
                if (typeof audioPub.audioTrack.setVolume === 'function') {
                    audioPub.audioTrack.setVolume(volume);
                }
                if (audioPub.audioTrack.attachedElements) {
                    audioPub.audioTrack.attachedElements.forEach(element => {
                        element.volume = volume;
                    });
                }
            }
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

        if (user_csrf_token !== null) {
            post_data["csrf_token"] = user_csrf_token;
        }

        if (user_login_session_id !== null && user_access_code !== null && user_session_time_stamp !== null) {
            post_data["login_session_id"] = user_login_session_id;
            post_data["access_code"] = user_access_code;
            post_data["session_time_stamp"] = user_session_time_stamp;
        }

        $.post(api_request_url, post_data);
        const $targetBox = $(`#live_cam_box_${live_cam_id}`);

        $targetBox.find('audio, video').each(function() {
            this.pause();
            this.srcObject = null;
        });

        $targetBox.remove();
        $(`#audio_${live_cam_id}`).remove();
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
        if (typeof live_cam_active_polls !== 'undefined') {
            Object.keys(live_cam_active_polls).forEach(user_id => {
                live_cam_stop_polling(user_id);
            });
        }

        if (live_cam_room) {

            let base_data = {
                group_id: live_cam_group_id
            };
            if (user_csrf_token !== null) base_data["csrf_token"] = user_csrf_token;
            if (user_login_session_id !== null && user_access_code !== null) {
                base_data["login_session_id"] = user_login_session_id;
                base_data["access_code"] = user_access_code;
                base_data["session_time_stamp"] = user_session_time_stamp;
            }

            fetch(api_request_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    ...base_data, remove: 'live_camera'
                }),
                keepalive: true
            });

            fetch(api_request_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    ...base_data, remove: 'live_camera_viewers', all_targets: true
                }),
                keepalive: true
            });

            await live_cam_room.disconnect();
            live_cam_room = null;
            stopPrivatePolling();
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
        console.error("Error during end_live_camera:", err);
    } finally {
        setTimeout(() => {
            window.is_ending_live = false;
        }, 2000);
    }
}

$(document).on('change', '.main .middle > .live_camera_interface > .live_cam_settings_box .select_camera', async function() {
    if (!live_cam_room || !live_cam_room.localParticipant) {
        console.warn("No active live cam room or participant");
        return;
    }

    const local = live_cam_room.localParticipant;
    const selectedDeviceId = $(this).val();

    if (!selectedDeviceId) return;

    try {
        if (typeof preferredCamId !== 'undefined') {
            preferredCamId = selectedDeviceId;
        }

        if (local.isScreenShareEnabled) {
            console.log("Screen sharing active, camera not switched");
            return;
        }

        await local.setCameraEnabled(false);

        await new Promise(r => setTimeout(r, 100));

        const tracks = await LivekitClient.createLocalTracks({
            audio: false,
            video: {
                deviceId: selectedDeviceId
            }
        });

        const videoTrack = tracks.find(track => track.kind === 'video');

        if (!videoTrack) {
            console.error("No video track created");
            return;
        }

        let frameCheck = 0;
        while (!videoTrack.attachedElements?.length && frameCheck < 20) {
            await new Promise(r => setTimeout(r, 100));
            frameCheck++;
        }

        const currentVideoPub = local.getTrackPublication(LivekitClient.Track.Source.Camera);

        if (currentVideoPub) {
            await local.unpublishTrack(currentVideoPub.track, true);
        }

        await local.publishTrack(videoTrack);

        if (!local.isCameraEnabled) {
            await local.setCameraEnabled(true);
        }

        live_cam_render_video(
            videoTrack,
            null,
            local.identity,
            true,
            current_live_privacy === 'private',
            true
        );

    } catch (error) {
        console.error("Failed to switch camera:", error);
        if (!local.isCameraEnabled) {
            await local.setCameraEnabled(true);
        }
    }
});

$(document).on('change', '.main .middle > .live_camera_interface > .live_cam_settings_box .select_microphone', async function() {
    if (!live_cam_room || !live_cam_room.localParticipant) {
        console.warn("No active live cam room or participant");
        return;
    }

    const local = live_cam_room.localParticipant;
    const selectedDeviceId = $(this).val();

    if (!selectedDeviceId) return;

    try {
        if (typeof preferredMicId !== 'undefined') {
            preferredMicId = selectedDeviceId;
        }

        const tracks = await LivekitClient.createLocalTracks({
            audio: {
                deviceId: selectedDeviceId,
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            },
            video: false
        });

        const audioTrack = tracks.find(track => track.kind === 'audio');

        if (!audioTrack) {
            console.error("No audio track created");
            return;
        }

        const currentAudioPub = local.getTrackPublication(LivekitClient.Track.Source.Microphone);

        if (currentAudioPub) {
            await local.unpublishTrack(currentAudioPub.track, true);
        }

        await local.publishTrack(audioTrack);

        const $micBtn = $('#live_cam_toggle_mic');
        if (local.isMicrophoneEnabled) {
            $micBtn.removeClass('live_cam_off');
            $micBtn.find('i').removeClass('bi-mic-mute-fill').addClass('bi-mic-fill');
        }

    } catch (error) {
        console.error("Failed to switch microphone:", error);

        try {
            if (!local.isMicrophoneEnabled) {
                await local.setMicrophoneEnabled(true);
            }
        } catch (recoveryError) {
            console.error("Failed to recover microphone after switch error:", recoveryError);
        }
    }
});


$(document).on('click', '#live_cam_hide_chat', function() {
    $('.main .middle > .live_camera_interface').toggleClass('full_height');
});

$(document).on('click', '#live_cam_toggle_mic', async function() {
    if (!live_cam_room || !live_cam_room.localParticipant) return;

    const local = live_cam_room.localParticipant;
    const $btn = $(this);
    const isEnabled = local.isMicrophoneEnabled;

    try {
        await local.setMicrophoneEnabled(!isEnabled);

        if (!isEnabled) {
            $btn.removeClass('live_cam_off');
            $btn.find('i').removeClass('bi-mic-mute-fill').addClass('bi-mic-fill');
        } else {
            $btn.addClass('live_cam_off');
            $btn.find('i').removeClass('bi-mic-fill').addClass('bi-mic-mute-fill');
        }
    } catch (error) {
        console.error("Failed to toggle microphone:", error);
    }
});

$(document).on('click', '#live_cam_toggle_video', async function() {
    if (!live_cam_room || !live_cam_room.localParticipant) return;

    const local = live_cam_room.localParticipant;
    const $btn = $(this);
    const isEnabled = local.isCameraEnabled;

    try {
        await local.setCameraEnabled(!isEnabled);

        if (!isEnabled) {
            $btn.removeClass('live_cam_off');
            $btn.find('i').removeClass('bi-camera-video-off-fill').addClass('bi-camera-video-fill');
        } else {
            $btn.addClass('live_cam_off');
            $btn.find('i').removeClass('bi-camera-video-fill').addClass('bi-camera-video-off-fill');
        }
    } catch (error) {
        console.error("Failed to toggle camera:", error);
    }
});