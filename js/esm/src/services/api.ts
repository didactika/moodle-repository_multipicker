// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Authenticated browser transport.
 * @module repository_multipicker/services/api
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import type {Response} from '../types';
import Config from '@moodle/lms/core/config';

/**
 * Send a session-bound browser request.
 * @param {string} scope Session scope.
 * @param {Object} fields Request data.
 * @returns {Promise<Object>} Response.
 */
export const request = async(scope: string, fields: Record<string, string | number | string[]>): Promise<Response> => {
    const body = new URLSearchParams({scope, ajax: '1', sesskey: Config.sesskey});
    Object.entries(fields).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            value.forEach(item => body.append(key + '[]', item));
        } else {
            body.append(key, String(value));
        }
    });
    const response = await fetch(Config.wwwroot + '/repository/multipicker/browser.php', {
        method: 'POST', credentials: 'same-origin', body,
    });
    const result = await response.json() as Response;
    if (!response.ok || result.error) {
        throw new Error(result.error || response.statusText);
    }
    return result;
};
