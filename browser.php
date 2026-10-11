<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Embedded standalone chooser and session-bound browser requests.
 *
 * @package repository_multipicker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', !empty($_POST['ajax']));
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/repository/lib.php');
require_login();
$token = required_param('scope', PARAM_ALPHANUM);
$scope = \repository_multipicker\local\browser::scope($token, optional_param('draftid', 0, PARAM_INT));
$PAGE->set_context(context::instance_by_id($scope['contextid']));
$PAGE->set_url('/repository/multipicker/browser.php', ['scope' => $token]);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('pluginname', 'repository_multipicker'));

if (optional_param('ajax', 0, PARAM_BOOL)) {
    require_sesskey();
    header('Content-Type: application/json; charset=utf-8');
    $renderer = $PAGE->get_renderer('core');
    try {
        $action = required_param('action', PARAM_ALPHA);
        if ($action === 'selectall') {
            $path = optional_param('path', '', PARAM_RAW);
            $query = optional_param('query', '', PARAM_TEXT);
            $repository = repository::get_repository_by_id($scope['repositoryid'], $scope['contextid']);
            $sources = [];
            for ($page = 0; $page < 100; $page++) {
                $listing = $repository->get_native_listing($path, $query, $page);
                foreach ($listing['list'] as $node) {
                    $sources[] = $node['path'] ?? $node['source'];
                }
                if (count($sources) > 500) {
                    throw new moodle_exception('limit', 'repository_multipicker');
                }
                if ($query === '' || empty($listing['pages'])) {
                    break;
                }
            }
            echo json_encode(['sources' => array_values(array_unique($sources))]);
        } else if ($action === 'reviewchildren') {
            $snapshot = $SESSION->repository_multipicker[$token]['preview'] ?? null;
            if (!$snapshot) {
                throw new moodle_exception('unavailable', 'repository_multipicker');
            }
            $data = \repository_multipicker\output\review::level(
                $snapshot['operations'], optional_param('aliases', false, PARAM_BOOL),
                optional_param('path', '/', PARAM_PATH), optional_param('page', 0, PARAM_INT)
            );
            echo json_encode(['html' => $renderer->render_from_template('repository_multipicker/browser/review', $data)]);
        } else if ($action === 'list') {
            $listing = \repository_multipicker\local\browser::listing(
                $scope,
                optional_param('path', '', PARAM_RAW),
                optional_param('page', 0, PARAM_INT),
                optional_param('query', '', PARAM_TEXT)
            );
            echo json_encode(['html' => $renderer->render_from_template('repository_multipicker/browser/results', $listing),
                'parent' => $listing['parent'], 'page' => $listing['page'], 'next' => $listing['next'],
                'previous' => $listing['previous'], 'location' => $listing['location']]);
        } else if (in_array($action, ['inspect', 'review', 'approve'], true)) {
            $sources = required_param_array('sources', PARAM_RAW);
            $maxfiles = required_param('maxfiles', PARAM_INT);
            $basepath = optional_param('basepath', '/', PARAM_PATH);
            $preservefolders = optional_param('preservefolders', true, PARAM_BOOL);
            $excluded = optional_param_array('excluded', [], PARAM_RAW);
            $copymode = optional_param('copymode', 'copy', PARAM_ALPHA);
            if (!in_array($copymode, ['copy', 'reference'], true)) {
                throw new moodle_exception('unavailable', 'repository_multipicker');
            }
            $options = compact('sources', 'maxfiles', 'basepath', 'preservefolders', 'excluded');
            $snapshot = \repository_multipicker\local\preview::prepare($token, $scope, $options, $action === 'approve');
            $removed = optional_param_array('removed', [], PARAM_RAW);
            if ($removed) {
                $kept = optional_param_array('kept', [], PARAM_RAW);
                $excluded = array_values(array_unique(array_merge($excluded,
                    \repository_multipicker\local\preview::exclusions($snapshot['operations'], $removed, $kept))));
                $options['excluded'] = $excluded;
                $snapshot = \repository_multipicker\local\preview::prepare($token, $scope, $options);
            }
            $operations = $snapshot['operations'];
            $counts = $snapshot['counts'];
            if ($action === 'inspect') {
                echo json_encode(['count' => count($operations), 'counts' => $counts]);
                exit;
            }
            if (!$operations && $action === 'approve') {
                throw new moodle_exception('nothing', 'repository_multipicker');
            }
            unset($SESSION->repository_multipicker[$token]['approval']);
            if ($action === 'approve') {
                $SESSION->repository_multipicker[$token]['approval'] = $operations;
                $SESSION->repository_multipicker[$token]['sources'] = $sources;
                $SESSION->repository_multipicker[$token]['maxfiles'] = $maxfiles;
                $SESSION->repository_multipicker[$token]['basepath'] = $basepath;
                $SESSION->repository_multipicker[$token]['preservefolders'] = $preservefolders;
                $SESSION->repository_multipicker[$token]['copymode'] = $copymode;
                $SESSION->repository_multipicker[$token]['excluded'] = $excluded;
                $SESSION->repository_multipicker[$token]['approvalnonce'] = bin2hex(random_bytes(16));
            }
            $source = 'batch:' . $token . ':' . ($SESSION->repository_multipicker[$token]['approvalnonce'] ?? '');
            echo json_encode(['html' => $action === 'approve' ? '' : $renderer->render_from_template(
                'repository_multipicker/browser/review',
                \repository_multipicker\output\review::level($operations, $copymode === 'reference')
            ),
                'excluded' => $excluded,
                'count' => count($operations),
                'counts' => $counts,
                'file' => $action === 'approve' ? ['title' => $operations[0]['filename'], 'source' => $source,
                    'sourcekey' => sha1($source . repository::get_secret_key() . sesskey()),
                    'repository_id' => $scope['repositoryid'],
                    'thumbnail' => $renderer->image_url('f/document')->out(false)] : null]);
        } else {
            throw new moodle_exception('unavailable', 'repository_multipicker');
        }
    } catch (moodle_exception $exception) {
        http_response_code(400);
        echo json_encode(['error' => $exception->getMessage()]);
    }
    exit;
}
$PAGE->requires->js_call_amd('repository_multipicker/browser', 'init', [$token]);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'repository_multipicker/browser/shell',
    ['scope' => $token, 'name' => get_string('pluginname', 'repository_multipicker'), 'sesskey' => sesskey(),
        'searchhelp' => $OUTPUT->help_icon('search', 'repository_multipicker'),
        'copyhelp' => $OUTPUT->help_icon('copymode', 'repository_multipicker'),
        'foldershelp' => $OUTPUT->help_icon('preservefolders', 'repository_multipicker')]
);
echo $OUTPUT->footer();
