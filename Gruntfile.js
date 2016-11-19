module.exports = function(grunt) {
  grunt.loadNpmTasks('grunt-contrib-jshint');
  grunt.registerTask('travis', [
    'jshint',
  ]);
};
