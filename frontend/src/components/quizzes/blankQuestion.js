/**
 * A blank question row for the quiz editor.
 *
 * Lives outside the component file so the editor keeps exporting components
 * only, as the fast-refresh rule requires.
 */
export const blankQuestion = () => ({
  prompt: '',
  options: ['', '', '', ''],
  correct_option: 0,
  points: 1,
})
