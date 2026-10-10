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

require(__DIR__ . '/../../config.php');
require_login();
$token = required_param('scope', PARAM_ALPHANUM);
$scope = \repository_multipicker\local\browser::scope($token);
$PAGE->set_context(context::instance_by_id($scope['contextid']));
$PAGE->set_url('/repository/multipicker/browser.php', ['scope' => $token]);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title($scope['name']);

if (optional_param('ajax', 0, PARAM_BOOL)) {
    require_sesskey();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $action = required_param('action', PARAM_ALPHA);
        if ($action === 'list') {
            $listing = \repository_multipicker\local\browser::listing(
                $scope,
                optional_param('path', '', PARAM_RAW),
                optional_param('page', 0, PARAM_INT)
            );
            echo json_encode(['html' => $OUTPUT->render_from_template('repository_multipicker/browser/results', $listing),
                'parent' => $listing['parent'], 'page' => $listing['page'], 'next' => $listing['next'],
                'previous' => $listing['previous'], 'location' => $listing['location']]);
        } else if ($action === 'review' || $action === 'approve') {
            $sources = required_param_array('sources', PARAM_RAW);
            $maxfiles = required_param('maxfiles', PARAM_INT);
            $basepath = optional_param('basepath', '/', PARAM_PATH);
            $operations = \repository_multipicker\local\browser::review($scope, $sources, $maxfiles, $basepath);
            if (!$operations) {
                throw new moodle_exception('nothing', 'repository_multipicker');
            }
            unset($SESSION->repository_multipicker[$token]['approval']);
            if ($action === 'approve') {
                $SESSION->repository_multipicker[$token]['approval'] = $operations;
                $SESSION->repository_multipicker[$token]['sources'] = $sources;
                $SESSION->repository_multipicker[$token]['maxfiles'] = $maxfiles;
                $SESSION->repository_multipicker[$token]['basepath'] = $basepath;
            }
            echo json_encode(['html' => $OUTPUT->render_from_template(
                'repository_multipicker/browser/review',
                ['files' => $operations, 'count' => count($operations)]
            ),
                'file' => ['title' => $operations[0]['filename'], 'source' => 'batch:' . $token,
                    'repository_id' => $scope['repositoryid'], 'thumbnail' => $OUTPUT->image_url('f/document')->out(false)]]);
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
    ['scope' => $token, 'name' => format_string($scope['name']), 'sesskey' => sesskey()]
);
echo $OUTPUT->footer();
