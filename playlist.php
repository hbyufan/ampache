<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU General Public License, version 2 (GPLv2)
 * Copyright 2001 - 2014 Ampache.org
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License v2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

// This is playlist.php, it does playlist things.

require_once 'lib/init.php';
// We special-case this so we can send a 302 if the delete succeeded
if ($_REQUEST['action'] == 'delete_playlist') {
    // Check rights
    $playlist = new Playlist($_REQUEST['playlist_id']);
    if ($playlist->has_access()) {
        $playlist->delete();
        // Go elsewhere
        header('Location: ' . AmpConfig::get('web_path') . '/browse.php?action=playlist');
        exit;
    }
}

UI::show_header();

/* Switch on the action passed in */
switch ($_REQUEST['action']) {
    case 'add_dyn_song':
        /* Check Rights */
        if (!$playlist->has_access()) {
            UI::access_denied();
            break;
        }

        $playlist->add_dyn_song();
        $_SESSION['data']['playlist_id'] = $playlist->id;
    break;
    case 'create_playlist':
        /* Check rights */
        if (!Access::check('interface','25')) {
            UI::access_denied();
            break;
        }

        $playlist_name = scrub_in($_REQUEST['playlist_name']);
        $playlist_type = scrub_in($_REQUEST['type']);

        $playlist->create($playlist_name, $playlist_type);
        $_SESSION['data']['playlist_id'] = $playlist->id;
        show_confirmation(T_('Playlist Created'), sprintf(T_('%1$s (%2$s) has been created'), $playlist_name, $playlist_type), 'playlist.php');
    break;
    case 'delete_playlist':
        // If we made it here, we didn't have sufficient rights.
        UI::access_denied();
    break;
    case 'remove_song':
        /* Check em for rights */
        if (!$playlist->has_access()) {
            UI::access_denied();
            break;
        }
        $playlist->remove_songs($_REQUEST['song']);
    break;
    case 'show_playlist':
        $playlist = new Playlist($_REQUEST['playlist_id']);
        $playlist->format();
        $object_ids = $playlist->get_items();
        require_once AmpConfig::get('prefix') . '/templates/show_playlist.inc.php';
    break;
    case 'show_import_playlist':
        require_once AmpConfig::get('prefix') . '/templates/show_import_playlist.inc.php';
    break;
    case 'import_playlist':
        /* first we rename the file to it's original name before importing.
        Otherwise the playlist name will have the $_FILES['filename']['tmp_name'] which doesn't look right... */
        $dir = dirname($_FILES['filename']['tmp_name']) . "/";
        $filename = $dir . basename($_FILES['filename']['name']);
        move_uploaded_file($_FILES['filename']['tmp_name'], $filename );

        $result = Catalog::import_playlist($filename);

        if ($result['success']) {
            $url = 'show_playlist&amp;playlist_id=' . $result['id'];
            $title = T_('Playlist Imported');
            $body  = basename($_FILES['filename']['name']);
            $body .= '<br />' .
                sprintf(ngettext('Successfully imported playlist with %d song.', 'Successfully imported playlist with %d songs.', $result['count']), $result['count']);
        } else {
            $url = 'show_import_playlist';
            $title = T_('Playlist Not Imported');
            $body = T_($result['error']);
        }
        show_confirmation($title, $body, AmpConfig::get('web_path') . '/playlist.php?action=' . $url);
    break;
    case 'set_track_numbers':
        debug_event('playlist', 'Set track numbers called.', '5');

        /* Make sure they have permission */
        if (!$playlist->has_access()) {
            UI::access_denied();
            break;
        }

        // Retrieving final song order from url
        foreach ($_GET as $key => $data) {
            $_GET[$key] = unhtmlentities(scrub_in($data));
            debug_event('playlist', $key.'='.$_GET[$key], '5');
        }

        $playlist = new Playlist($_REQUEST['playlist_id']);
        if (isset($_GET['order'])) {
            $songs = explode(";", $_GET['order']);
            $track = 1;
            foreach ($songs as $song_id) {
                if ($song_id != '') {
                    $playlist->update_track_number($song_id, $track);
                    ++$track;
                }
            }
        }
    break;
    case 'add_song':
        if (!$playlist->has_access()) {
            UI::access_denied();
            break;
        }

        $playlist = new Playlist($_REQUEST['playlist_id']);
        $playlist->add_songs(array($_REQUEST['song_id']), 'ORDERED');
    break;
    case 'prune_empty':
        if (!$GLOBALS['user']->has_access(100)) {
            UI::access_denied();
            break;
        }

        prune_empty_playlists();
        $url = AmpConfig::get('web_path') . '/playlist.php';
        $title = T_('Empty Playlists Deleted');
        $body  = '';
        show_confirmation($title,$body,$url);
    break;
    default:
        require_once AmpConfig::get('prefix') . '/templates/show_playlist.inc.php';
    break;
} // switch on the action

UI::show_footer();
