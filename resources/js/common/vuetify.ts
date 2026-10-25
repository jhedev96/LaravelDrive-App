/**
 * Vuetify 4 factory with the legacy theme carryover (W5.2).
 *
 * Legacy `resources/assets/admin/src/vuetify.js` light/dark themes are
 * carried verbatim (indigo family + header/nav/footer custom keys).
 */
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';
import 'vuetify/styles';

export function createDriveVuetify() {
    return createVuetify({
        theme: {
            defaultTheme: 'light',
            themes: {
                light: {
                    colors: {
                        primary: '#3F51B5', // colors.indigo
                        secondary: '#283593', // indigo.darken3
                        accent: '#5C6BC0', // indigo.accent1
                        error: '#E53935', // red.darken1
                        warning: '#FDD835', // yellow.darken1
                        info: '#E3F2FD', // blue.lighten5
                        success: '#43A047', // green.darken1
                        header: '#3949AB', // indigo.darken1
                        nav: '#E0E0E0', // grey.lighten4
                        footer: '#7986CB', // indigo.lighten3
                    },
                },
                dark: {
                    dark: true,
                    colors: {
                        primary: '#C5CAE9', // indigo.lighten5
                        secondary: '#283593',
                        accent: '#5C6BC0',
                        error: '#E53935',
                        warning: '#FDD835',
                        info: '#E3F2FD',
                        success: '#43A047',
                        header: '#1A237E', // indigo.darken4
                        nav: '#283593', // indigo.darken2
                        footer: '#7986CB',
                    },
                },
            },
        },
    });
}
