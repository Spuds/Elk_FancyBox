/**
 * @package "FancyBox 4 ElkArte" Addon for Elkarte
 * @author Spuds
 * @copyright (c) 2011-2024 Spuds
 * @license This Source Code is subject to the terms of the Mozilla Public License
 * version 1.1 (the "License"). You can obtain a copy of the License at
 * http://mozilla.org/MPL/1.1/.
 *
 * @version 1.0.9
 *
 */

/**
 * Waits for a specific event to be attached to an element within a given time limit.
 *
 * The default click.elk_lb and click.elk_bbc events are defered. In order to turn them .off we need to ensure they are .on
 *
 * @param {string} selector - The jQuery selector used to select the element to monitor for the event.
 * @param {string} eventSpace - The event to wait for, along with its namespace. The eventSpace format should be "event.namespace".
 * @param {number} [interval=1000] - The interval in milliseconds to check for the event on the element.
 * @param {number} [maxAttempts=10] - The maximum number of attempts to check for the event before giving up.
 * @returns {Promise} - A Promise that resolves to true if the event is attached within the time limit, and rejects with an Error if the event is not attached.
 */
function fbWaitForEvent(selector, eventSpace, interval = 1000, maxAttempts = 10)
{
	let attempts = 0,
		[eventName, nameSpace] = eventSpace.split(".");

	if ($(selector).length === 0)
	{
		return new Promise((resolve, reject) => {
			reject(new Error("Event " + eventSpace + " was not found."));
		});
	}

	return new Promise((resolve, reject) => {
		const intervalId = setInterval(() => {
			attempts++;

			const events = $._data($(selector)[0], "events"); // I beleive, not offically supported

			// The event, like click, exits on the element
			if (typeof events !== 'undefined' && events[eventName])
			{
				// There could be more than one due to async/defer, see if its the desired namespace
				events[eventName].forEach((event) => {
					if (event && event.namespace === nameSpace)
					{
						clearInterval(intervalId);
						resolve(true);
					}
				});
			}
			else if (attempts >= maxAttempts)
			{
				clearInterval(intervalId);
				reject(new Error("Event " + eventSpace + " was not attached within the time limit."));
			}
		}, interval);
	});
}
