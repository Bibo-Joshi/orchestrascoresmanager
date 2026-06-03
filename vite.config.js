import { createAppConfig } from "@nextcloud/vite-config";
import { join, resolve } from "path";
import eslint from "vite-plugin-eslint";
import stylelint from "vite-plugin-stylelint";

const isProduction = process.env.NODE_ENV === "production";

export default createAppConfig(
  {
    main: resolve(join("src", "main.ts")),
    adminSettings: resolve(join("src", "admin-settings.ts")),
  },
  {
  	config: {
  		css: {
  			modules: {
  				localsConvention: "camelCase",
  			},
  		},
  		plugins: [eslint(), stylelint()],
  		resolve: {
  			alias: {
  				"@": resolve("src"),
  			},
  		},
  		build: {
  			cssCodeSplit: true,
  		},
		// don't copy contents of the /public folder as we just include the
		// /public folder in the distribution and don't want to have duplicates
		publicDir: false,
  	},
  	minify: isProduction,
    createEmptyCSSEntryPoints: true,
    extractLicenseInformation: true,
    thirdPartyLicense: false,
  }
);
