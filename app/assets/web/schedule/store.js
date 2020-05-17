'use strict';

import Vue from "vue";
import Vuex from "vuex";
import axios from "axios";
import VueAxios from "vue-axios"

var COLOR_VOLUNTARY = '#0077F7';
var COLOR_MANDATORY = '#D53343';
var COLOR_ATTENDS = '#27A243';
var COLOR_BLOCKED = '#6C757D';

Vue.use(Vuex);
Vue.use(VueAxios, axios);
Vue.axios.defaults.baseURL = basePath + '/api/schedule/';

export default new Vuex.Store({
    state: {
        config: {
            seminar_from_date: "2000-01-01",
            seminar_to_date: "2000-01-01",
            min_time: "0",
            max_time: "24"
        },
        blocks: [],
        resources: [],
        events: [],
        eventsMap: [],
        message: null,
        loading: 0,
        notRegisteredMandatoryPrograms: 0
    },
    mutations: {
        setConfig(state, config) {
            state.config = config;
        },
        setBlocks(state, blocks) {
            state.blocks = blocks;
        },
        setResources(state, resources) {
            state.resources = resources;
        },
        setEvents(state, events) {
            state.events = events;
        },
        setEventsMap(state, eventsMap) {
            state.eventsMap = eventsMap;
        },
        setEventUserAttends(state, info) {
            const event = state.eventsMap[info.eventId];
            if (event) {
                event.extendedProps.userAttends = info.userAttends;
                event.color = getColor(event);
            }
        },
        setEventAttendeesCount(state, info) {
            const event = state.eventsMap[info.eventId];
            if (event) {
                event.extendedProps.attendeesCount = info.attendeesCount;
                event.extendedProps.occupied = event.extendedProps.block.capacity !== null && event.extendedProps.block.capacity <= info.attendeesCount;
            }
        },
        setEventBlocked(state, info) {
            const event = state.eventsMap[info.eventId];
            if (event) {
                event.extendedProps.blocked = info.blocked;
                event.color = getColor(event);
            }
        },
        setMessage(state, message) {
            state.message = message;
        },
        incrementLoading(state) {
            state.loading++;
        },
        decrementLoading(state) {
            state.loading--;
        },
        setNotRegisteredMandatoryPrograms(state, notRegisteredMandatoryPrograms) {
            state.notRegisteredMandatoryPrograms = notRegisteredMandatoryPrograms;
        },
        incrementNotRegisteredMandatoryPrograms(state) {
            state.notRegisteredMandatoryPrograms++;
        },
        decrementNotRegisteredMandatoryPrograms(state) {
            state.notRegisteredMandatoryPrograms++;
        }
    },
    actions: {
        /**
         * Načte nastavení kalendáře, bloky, místnosti a programy.
         */
        loadData({commit}) {
            commit('incrementLoading');
            Vue.axios.get('get-calendar-config')
                .then(response => {
                    const config = JSON.parse(response.data);
                    commit('setConfig', config);
                }).catch(error => {
                    handleError(commit, error);
                }).finally(() => {
                    commit('decrementLoading');
                });

            commit('incrementLoading');
            axios.all([
                Vue.axios.get('get-blocks'),
                Vue.axios.get('get-rooms'),
                Vue.axios.get('get-programs-web')
            ]).then(axios.spread((blocksResponse, roomsResponse, programsResponse) => {
                const blocks = Array.prototype.slice.call(JSON.parse(blocksResponse.data))
                    .map(function(block) {
                        return {
                            id: String(block.id),
                            name: block.name,
                            category: block.category,
                            lectors: block.lectors,
                            lectorsNames: block.lectors_names,
                            capacity: block.capacity,
                            mandatory: block.mandatory,
                            autoRegistered: block.auto_registered,
                            userAllowed: block.user_allowed,
                            userAttends: block.user_attends,
                            perex: block.perex,
                            description: block.description
                        }
                    });
                commit('setBlocks', blocks);

                let notRegisteredMandatoryPrograms = 0;
                blocks.forEach(function(block) {
                    if (block.mandatory && block.userAllowed && !block.userAttends) {
                        notRegisteredMandatoryPrograms++;
                    }
                });
                commit('setNotRegisteredMandatoryPrograms', notRegisteredMandatoryPrograms);

                const blocksMap = blocks
                    .reduce(function (map, obj) {
                        map[obj.id] = obj;
                        return map;
                    }, {});

                const resources = Array.prototype.slice.call(JSON.parse(roomsResponse.data))
                    .map(function(room) {
                        return {
                            id: String(room.id),
                            title: room.name
                        }
                    });
                resources.push({
                    id: "0",
                    title: 'Nepřiřazená'
                });
                commit('setResources', resources);

                const events = Array.prototype.slice.call(JSON.parse(programsResponse.data))
                    .map(function(program) {
                        const block = blocksMap[program.block_id];
                        return {
                            id: String(program.id),
                            resourceId: String(program.room_id || 0),
                            title: block.name,
                            start: program.start,
                            end: program.end,
                            extendedProps: {
                                block: block,
                                attendeesCount: program.attendees_count,
                                userAttends: program.user_attends,
                                blocks: program.blocks,
                                blocked: program.blocked,
                                paid: program.paid,
                                occupied: block.capacity !== null && block.capacity <= program.attendees_count
                            }
                        }
                    });
                commit('setEvents', events);

                events.forEach(function(event) {
                    event.color = getColor(event);
                });

                const eventsMap = events
                    .reduce(function (map, obj) {
                        map[obj.id] = obj;
                        return map;
                    }, {});
                commit('setEventsMap', eventsMap);
            })).catch(error => {
                handleError(commit, error);
            }).finally(() => {
                commit('decrementLoading');
            });
        },

        /**
         * Zpracuje přihlášení na program.
         */
        attendProgram({commit, state}, info) {
            commit('incrementLoading');
            Vue.axios.put('attend-program/' + info.event.id)
                .then(response => {
                    const responseObject = JSON.parse(response.data);

                    commit('setEventUserAttends', {eventId: info.event.id, userAttends: true});
                    commit('setEventAttendeesCount', {eventId: info.event.id, attendeesCount: responseObject.program.attendees_count});

                    info.event.extendedProps.blocks.forEach(function(eventId) {
                        commit('setEventBlocked', {eventId: eventId, blocked: true});
                    });

                    if (info.event.extendedProps.block.mandatory) {
                        commit('decrementNotRegisteredMandatoryPrograms');
                    }

                    commit('setMessage', {type: responseObject.status, text: responseObject.message});
                }).catch(error => {
                    handleError(commit, error);
                }).finally(() => {
                    commit('decrementLoading');
                });
        },

        /**
         * Zpracuje odhlášení z programu.
         */
        unattendProgram({commit, state}, info) {
            commit('incrementLoading');
            Vue.axios.delete('unattend-program/' + info.event.id)
                .then(response => {
                    const responseObject = JSON.parse(response.data);

                    commit('setEventUserAttends', {eventId: info.event.id, userAttends: false});
                    commit('setEventAttendeesCount', {eventId: info.event.id, attendeesCount: responseObject.program.attendees_count});

                    state.events.forEach(function(event) {
                        commit('setEventBlocked', {eventId: event.id, blocked: false});
                    });
                    state.events.forEach(function(event) {
                        if (event.extendedProps.userAttends) {
                            event.extendedProps.blocks.forEach(function (eventId) {
                                if (event.id !== eventId) {
                                    commit('setEventBlocked', {eventId: eventId, blocked: true});
                                }
                            });
                        }
                    });

                    if (info.event.extendedProps.block.mandatory) {
                        commit('incrementNotRegisteredMandatoryPrograms');
                    }

                    commit('setMessage', {type: responseObject.status, text: responseObject.message});
                }).catch(error => {
                    handleError(commit, error);
                }).finally(() => {
                    commit('decrementLoading');
                });
        }
    }
});

function handleError(commit, error) {
    if (error.response && error.response.data) {
        const responseObject = JSON.parse(error.response.data);
        commit('setMessage', {type: responseObject.status, text: responseObject.message});
    } else {
        commit('setMessage', {type: 'danger', text: 'Neznámá chyba.'});
    }
}

function getColor(event) {
    if (event.extendedProps.userAttends) {
        return COLOR_ATTENDS;
    } else if (!userAllowedRegisterPrograms || event.extendedProps.occupied || event.extendedProps.blocked || !event.extendedProps.paid) {
        return COLOR_BLOCKED;
    } else if (event.extendedProps.block.mandatory) {
        return COLOR_MANDATORY;
    } else {
        return COLOR_VOLUNTARY;
    }
}